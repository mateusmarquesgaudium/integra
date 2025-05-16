<?php

use src\geral\RedisService;
use src\AnotaAi\Http\OrderInTransit;
use src\geral\Enums\RequestConstants;
use Tests\Helpers\MockHelpers\RequestMockHelper;
use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);
    $this->orderInTransit = new OrderInTransit($this->redisClient);

    $this->orderId = fake()->uuid();
    $this->merchantId = fake()->uuid();
    $this->authorizationToken = fake()->sha256();
});

afterEach(function () {
    Mockery::close();
});

test('orderId vazio', function () {
    $this->redisClient->expects($this->never())->method('get');

    $event = [];
    $pipeline = [];
    $result = $this->orderInTransit->send($event, $pipeline);
    expect($result)->toBeFalse();
});

test('orderId não está no Redis', function () {
    $this->redisClient->expects($this->once())->method('get')->willReturn(null);

    $event = ['orderId' => $this->orderId];
    $pipeline = [];
    $result = $this->orderInTransit->send($event, $pipeline);
    expect($result)->toBeTrue();
});

test('excedeu limite de taxa (ratelimit)', function () {
    $this->redisClient->expects($this->once())->method('get')->willReturn(json_encode(['merchant_id' => $this->merchantId]));
    $this->redisClient->method('zRemRangeByScore')->willReturn(1);
    $this->redisClient->method('zCard')->willReturn(501);

    $event = ['orderId' => $this->orderId];
    $pipeline = [];
    $result = $this->orderInTransit->send($event, $pipeline);
    expect($result)->toBeFalse();
});

test('erro diferente de HTTP_OK', function () {
    $merchantData = [
        'merchant_id' => $this->merchantId,
        'merchant_token' => $this->authorizationToken,
    ];
    $this->redisClient->expects($this->once())->method('get')->willReturn(json_encode($merchantData));
    $this->redisClient->method('zRemRangeByScore')->willReturn(1);
    $this->redisClient->method('zCard')->willReturn(1);
    $this->redisClient->method('zAdd')->willReturn(1);

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_FORBIDDEN, []);

    $event = ['orderId' => $this->orderId];
    $pipeline = [];
    $result = $this->orderInTransit->send($event, $pipeline);
    expect($result)->toBeFalse();
});

test('requisição correta', function () {
    $merchantData = [
        'merchant_id' => $this->merchantId,
        'merchant_token' => $this->authorizationToken,
    ];
    $this->redisClient->expects($this->once())->method('get')->willReturn(json_encode($merchantData));
    $this->redisClient->method('zRemRangeByScore')->willReturn(1);
    $this->redisClient->method('zCard')->willReturn(1);
    $this->redisClient->method('zAdd')->willReturn(1);

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, []);

    $event = ['orderId' => $this->orderId];
    $pipeline = [];
    $result = $this->orderInTransit->send($event, $pipeline);
    expect($result)->toBeTrue();
});
