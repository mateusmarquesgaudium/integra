<?php

use src\Aiqfome\Entities\OrderCache;
use src\Aiqfome\Enums\RedisSchema;
use src\Cache\Entities\CacheValidator;
use src\Delivery\Enums\RedisSchema as DeliveryRedisSchema;
use src\geral\RedisService;
use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);
    $this->cacheValidator = $this->createMock(CacheValidator::class);
    $this->orderCache = new OrderCache($this->redisClient, $this->cacheValidator);

    $this->merchantId = fake()->uuid();
    $this->orderId = fake()->uuid();
});

test('adiciona evento ao webhook para uma integração válida', function() {
    $this->cacheValidator->expects($this->once())
        ->method('validateCache')
        ->willReturn(true);

    $this->redisClient->expects($this->once())
        ->method('rPush')
        ->with(
            RedisSchema::KEY_LIST_ORDERS_EVENTS,
            $this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['order_id'] === $this->orderId;
            })
        );

    $this->orderCache->addEventOrderReadWebhook($this->merchantId, $this->orderId);
});

test('não adiciona evento ao webhook para uma integração inválida', function() {
    $this->cacheValidator->expects($this->once())
        ->method('validateCache')
        ->willReturn(false);

    $this->redisClient->expects($this->never())
        ->method('rPush');

    $this->orderCache->addEventOrderReadWebhook($this->merchantId, $this->orderId);
});

test('adiciona evento ready ao webhook', function() {
    $this->redisClient->expects($this->once())
        ->method('rPush')
        ->with(
            DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK,
            $this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['order_id'] === $this->orderId;
            })
        );

    $this->orderCache->addEventOrderReadyWebhook($this->merchantId, $this->orderId);
});

test('adiciona evento cancel ao webhook', function() {
    $this->redisClient->expects($this->once())
        ->method('rPush')
        ->with(
            DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK,
            $this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['order_id'] === $this->orderId;
            })
        );

    $this->orderCache->addEventOrderCancelWebhook($this->merchantId, $this->orderId);
});
