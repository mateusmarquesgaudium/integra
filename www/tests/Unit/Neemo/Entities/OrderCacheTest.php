<?php

use src\geral\RedisService;
use src\Neemo\Entities\OrderCache;
use src\Neemo\Enums\RedisSchema;
use src\Delivery\Enums\Provider;
use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);
    $this->orderCache = new OrderCache($this->redisClient);
    $this->merchantId = fake()->uuid();
    $this->orderId = fake()->uuid();
});

test('credencial não encontrada', function () {
    $this->redisClient->expects($this->once())
        ->method('exists')
        ->willReturn(false);
    $this->redisClient->expects($this->never())
        ->method('rPush');

    $this->orderCache->addEventOrderWebhook($this->merchantId, $this->orderId);
});

test('evento pedido criado', function () {
    $this->redisClient->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->redisClient->expects($this->once())
        ->method('rPush')
        ->with(
            RedisSchema::KEY_LIST_PENDING_ORDER_EVENTS,
            $this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['merchant_id'] === $this->merchantId
                    && $data['event_created_at'] === date('Y-m-d\TH:i:s\Z')
                    && $data['provider'] === Provider::NEEMO
                    && $data['order_id'] === $this->orderId;
            })
        );

    $this->orderCache->addEventOrderWebhook($this->merchantId, $this->orderId);
});