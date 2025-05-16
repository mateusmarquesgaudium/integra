<?php

namespace src\Opendelivery\Entities;

use src\Delivery\Enums\RedisSchema as DeliveryRedisSchema;
use src\geral\RedisService;
use src\Opendelivery\Enums\RedisSchema;

class OrderCache
{
    private RedisService $redisService;
    private string $provider;

    public function __construct(RedisService $redisService, string $provider)
    {
        $this->redisService = $redisService;
        $this->provider = $provider;
    }

    public function addEventOrderWebhook(array $order): void
    {
        $this->redisService->rPush(DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, json_encode($order));
    }

    public function addOrderCache(string $orderId, array $order): void
    {
        $key = $this->getOrderDetailsKey($orderId, RedisSchema::KEY_ORDER_DETAILS);
        $this->redisService->set($key, json_encode($order));
        $this->expireOrderCache($orderId);
    }

    public function getOrderCache(string $orderId): ?array
    {
        return json_decode($this->redisService->get($this->getOrderDetailsKey($orderId, RedisSchema::KEY_ORDER_DETAILS)), true);
    }

    public function expireOrderCache(string $orderId): void
    {
        $this->redisService->expire($this->getOrderDetailsKey($orderId, RedisSchema::KEY_ORDER_DETAILS), RedisSchema::TTL_ORDER_DETAILS);
    }

    public function getOrderDetailsKey(string $orderId, string $keyBase): string
    {
        $key = str_replace(['{order_id}', '{provider}'], [$orderId, $this->provider], $keyBase);
        return $key;
    }
}