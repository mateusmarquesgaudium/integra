<?php

use src\DeliveryDireto\Enums\RedisSchema;
use src\geral\RedisService;
use src\DeliveryDireto\Http\OrderInTransit;
use src\geral\Enums\RequestConstants;
use Tests\Helpers\MockHelpers\RequestMockHelper;
use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);
    $this->orderInTransit = new OrderInTransit($this->redisClient);

    $this->orderId = fake()->uuid();
    $this->merchantId = fake()->uuid();
    $this->authorizationToken = fake()->sha256();
    $this->username = fake()->uuid();
    $this->password = fake()->password();

    $this->keyOrderDetails = str_replace('{order_id}', $this->orderId, RedisSchema::KEY_ORDER_DETAILS);
    $this->keyCredential = str_replace('{merchant_id}', $this->merchantId, RedisSchema::KEY_CRENDENTIAL_MERCHANT);

    $hashRedis = md5($this->username . $this->password . $this->merchantId);
    $this->keyOauth = str_replace('{hash}', $hashRedis, RedisSchema::KEY_OAUTH_ACCESS_TOKEN);
});

afterEach(function () {
    Mockery::close();
});

test('orderId vazio', function () {
    $this->redisClient->expects($this->never())->method('get')->with($this->keyOrderDetails);

    $event = [];
    $pipeline = [];
    $result = $this->orderInTransit->send($event, $pipeline);
    expect($result)->toBeFalse();
});

test('orderId não está no Redis', function () {
    $this->redisClient->expects($this->once())->method('get')->with($this->keyOrderDetails)->willReturn(null);

    $event = ['orderId' => $this->orderId];
    $pipeline = [];
    $result = $this->orderInTransit->send($event, $pipeline);
    expect($result)->toBeTrue();
});

test('excedeu limite de taxa (ratelimit)', function () {
    $this->redisClient->expects($this->once())->method('get')->with($this->keyOrderDetails)->willReturn(json_encode(['merchant_id' => $this->merchantId]));
    $this->redisClient->method('zRemRangeByScore')->willReturn(1);
    $this->redisClient->method('zCard')->willReturn(501);

    $event = ['orderId' => $this->orderId];
    $pipeline = [];
    $result = $this->orderInTransit->send($event, $pipeline);
    expect($result)->toBeFalse();
});

test('credenciais não encontradas', function () {
    $this->redisClient
        ->expects($this->exactly(2))
        ->method('get')->willReturnMap([
            [$this->keyOrderDetails, json_encode(['merchant_id' => $this->merchantId])],
            [$this->keyCredential, '']
        ]);
    $this->redisClient->method('zRemRangeByScore')->willReturn(1);
    $this->redisClient->method('zCard')->willReturn(1);

    $event = ['orderId' => $this->orderId];
    $pipeline = [];
    $result = $this->orderInTransit->send($event, $pipeline);
    expect($result)->toBeFalse();
});

test('erro diferente de sucesso', function () {
    $credentialsData = [
        'username' => $this->username,
        'password' => $this->password,
    ];
    $this->redisClient
        ->expects($this->exactly(3))
        ->method('get')->willReturnMap([
            [$this->keyOrderDetails, json_encode(['merchant_id' => $this->merchantId])],
            [$this->keyCredential, json_encode($credentialsData)],
            [$this->keyOauth, $this->authorizationToken]
        ]);
    $this->redisClient->method('zRemRangeByScore')->willReturn(1);
    $this->redisClient->method('zCard')->willReturn(1);

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_FORBIDDEN, []);

    $event = ['orderId' => $this->orderId];
    $pipeline = [];
    $result = $this->orderInTransit->send($event, $pipeline);
    expect($result)->toBeFalse();
});

test('requisição correta', function (int $httpCode) {
    $credentialsData = [
        'username' => $this->username,
        'password' => $this->password,
    ];
    $this->redisClient
        ->expects($this->exactly(3))
        ->method('get')->willReturnMap([
            [$this->keyOrderDetails, json_encode(['merchant_id' => $this->merchantId])],
            [$this->keyCredential, json_encode($credentialsData)],
            [$this->keyOauth, $this->authorizationToken]
        ]);
    $this->redisClient->method('zRemRangeByScore')->willReturn(1);
    $this->redisClient->method('zCard')->willReturn(1);

    RequestMockHelper::createExternalMock($httpCode, []);

    $event = ['orderId' => $this->orderId];
    $pipeline = [];
    $result = $this->orderInTransit->send($event, $pipeline);
    expect($result)->toBeTrue();
})->with([
    RequestConstants::HTTP_NO_CONTENT,
    RequestConstants::HTTP_UNPROCESSABLE_ENTITY,
    RequestConstants::HTTP_NOT_FOUND
]);
