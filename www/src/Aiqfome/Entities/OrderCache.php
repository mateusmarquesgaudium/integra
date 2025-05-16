<?php

namespace src\Aiqfome\Entities;

use DateTime;
use DateTimeZone;
use src\Aiqfome\Enums\RedisSchema;
use src\Cache\Entities\CacheValidator;
use src\Delivery\Enums\RedisSchema as DeliveryRedisSchema;
use src\Delivery\Enums\OrderStatus;
use src\Delivery\Enums\Provider;
use src\geral\RedisService;

class OrderCache
{
    private RedisService $redisService;
    private CacheValidator $cacheValidator;

    public function __construct(RedisService $redisService, CacheValidator $cacheValidator)
    {
        $this->redisService = $redisService;
        $this->cacheValidator = $cacheValidator;
    }

    public function addEventOrderReadWebhook(string $merchantId, string $orderId): void
    {
        if (!$this->cacheValidator->validateCache()) {
            return;
        }

        $dateUtc = new DateTime();
        $dateUtc->setTimezone(new DateTimeZone('UTC'));
        $orderCache = [
            'merchant_id' => $merchantId,
            'event_created_at' => $dateUtc->format('Y-m-d\TH:i:s\Z'),
            'provider' => Provider::AIQFOME,
            'order_id' => $orderId,
        ];

        $this->redisService->rPush(RedisSchema::KEY_LIST_ORDERS_EVENTS, json_encode($orderCache));
    }

    public function addEventOrderReadyWebhook(string $merchantId, string $orderId): void
    {
        $dateUtc = new DateTime();
        $dateUtc->setTimezone(new DateTimeZone('UTC'));

        $orderCache = [
            'event_created_at' => $dateUtc->format('Y-m-d\TH:i:s\Z'),
            'merchant_id' => $merchantId,
            'provider' => Provider::AIQFOME,
            'order_id' => $orderId,
            'order_status' => OrderStatus::IN_TRANSIT,
        ];

        $this->redisService->rPush(DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, json_encode($orderCache));
    }

    public function addEventOrderCancelWebhook(string $merchantId, string $orderId): void
    {
        $dateUtc = new DateTime();
        $dateUtc->setTimezone(new DateTimeZone('UTC'));

        $orderCache = [
            'event_created_at' => $dateUtc->format('Y-m-d\TH:i:s\Z'),
            'merchant_id' => $merchantId,
            'provider' => Provider::AIQFOME,
            'order_id' => $orderId,
            'order_status' => OrderStatus::HIDDEN,
        ];

        $this->redisService->rPush(DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, json_encode($orderCache));
    }
}