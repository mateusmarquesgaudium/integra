<?php

use src\geral\RedisService;
use src\Opendelivery\Entities\OrderEventDelivery;
use src\Opendelivery\Enums\RedisSchema;

beforeEach(function () {
    /** @var RedisService */
    $this->redisClient = Mockery::mock(RedisService::class);

    $this->orderEventDelivery = new OrderEventDelivery($this->redisClient);
});

afterEach(function () {
    Mockery::close();
});

test('adiciona evento de pedido no cache', function () {
    $data = ['event' => ['orderId' => '123', 'provider' => 'saipos']];
    $this->redisClient
        ->shouldReceive('rPush')
        ->with(RedisSchema::KEY_ORDERS_EVENTS, json_encode($data['event']))
        ->once();

    $this->orderEventDelivery->addOrderEventToCache($data);
});

test('não adiciona evento de pedido no cache', function () {
    $data = ['event' => []];
    $this->redisClient
        ->shouldReceive('rPush')
        ->never();

    $this->orderEventDelivery->addOrderEventToCache($data);
});