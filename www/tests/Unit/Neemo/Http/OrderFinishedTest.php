<?php

use src\Neemo\Enums\RedisSchema;
use src\geral\RedisService;
use src\Neemo\Http\OrderFinished;
use src\geral\Enums\RequestConstants;
use Tests\Helpers\MockHelpers\RequestMockHelper;
use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);
    $this->orderFinished = new OrderFinished($this->redisClient);

    $this->orderId = fake()->uuid();
    $this->merchantId = fake()->uuid();
    $this->keyOrderDetails = str_replace('{order_id}', $this->orderId, RedisSchema::KEY_ORDER_DETAILS);
});

afterEach(function () {
    Mockery::close();
});

test('orderId vazio', function () {
    $this->redisClient->expects($this->never())->method('get');

    $event = [];
    $pipeline = [];
    $result = $this->orderFinished->send($event, $pipeline);
    expect($result)->toBeFalse();
});

test('orderId não está no Redis', function () {
    $this->redisClient->expects($this->once())
        ->method('get')
        ->with($this->keyOrderDetails)
        ->willReturn(null);

    $event = ['orderId' => $this->orderId];
    $pipeline = [];
    $result = $this->orderFinished->send($event, $pipeline);
    expect($result)->toBeTrue();
});

test('erro diferente de HTTP_OK', function () {
    $this->redisClient->expects($this->once())
        ->method('get')
        ->with($this->keyOrderDetails)
        ->willReturn(json_encode(['merchant_id' => $this->merchantId]));
    $this->redisClient->expects($this->never())->method('del');

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_NOT_FOUND, []);

    $event = ['orderId' => $this->orderId];
    $pipeline = [];
    $result = $this->orderFinished->send($event, $pipeline);
    expect($result)->toBeFalse();
});

test('requisição válida HTTP_OK', function () {
    $this->redisClient->expects($this->once())
        ->method('get')
        ->with($this->keyOrderDetails)
        ->willReturn(json_encode(['merchant_id' => $this->merchantId]));

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, []);

    $event = ['orderId' => $this->orderId];
    $pipeline = [];
    $result = $this->orderFinished->send($event, $pipeline);
    expect($result)->toBeTrue();
    expect($pipeline)->toBe([['del', $this->keyOrderDetails]]);
});