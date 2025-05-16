<?php

use src\Delivery\Enums\OrderStatus;
use src\DeliveryDireto\Entities\OrderCache;
use src\geral\RedisService;
use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);
    $this->orderCache = new OrderCache($this->redisClient);

    $this->orderId = fake()->uuid();
    $this->merchantId = fake()->uuid();
});

test('pedido aprovado', function () {
    $this->redisClient->expects($this->once())->method('rPush')->willReturn(1);
    $this->orderCache->addEventOrderWebhook($this->merchantId, $this->orderId, OrderStatus::APPROVED);
});

test('pedido não aprovado sem detalhes no Redis', function () {
    $this->redisClient->expects($this->once())->method('get')->willReturn(null);
    $this->redisClient->expects($this->once())->method('rPush')->willReturn(1);
    $this->orderCache->addEventOrderWebhook($this->merchantId, $this->orderId, OrderStatus::IN_TRANSIT);
});

test('pedido não aprovado com detalhes no Redis', function () {
    $this->redisClient->expects($this->once())->method('get')->willReturn(json_encode(['order_id' => $this->orderId]));
    $this->redisClient->expects($this->once())->method('rPush')->willReturn(1);
    $this->orderCache->addEventOrderWebhook($this->merchantId, $this->orderId, OrderStatus::IN_TRANSIT);
});