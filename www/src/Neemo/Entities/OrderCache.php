<?php

namespace src\Neemo\Entities;

use DateTime;
use DateTimeZone;
use src\Delivery\Enums\Provider;
use src\Neemo\Enums\RedisSchema;
use src\geral\RedisService;

class OrderCache
{
    private RedisService $redisClient;

    public function __construct(RedisService $redisClient)
    {
        $this->redisClient = $redisClient;
    }

    public function addEventOrderWebhook(string $merchantId, string $orderId): void
    {
        $keyCacheCredential = str_replace('{merchant_id}', $merchantId, RedisSchema::KEY_CRENDENTIAL_MERCHANT);
        if (!$this->redisClient->exists($keyCacheCredential)) {
            return;
        }

        $dateUtc = new DateTime();
        $dateUtc->setTimezone(new DateTimeZone('UTC'));

        $orderCache = [
            'merchant_id' => $merchantId,
            'event_created_at' => $dateUtc->format('Y-m-d\TH:i:s\Z'),
            'provider' => Provider::NEEMO,
            'order_id' => $orderId,
        ];
        $this->redisClient->rPush(RedisSchema::KEY_LIST_PENDING_ORDER_EVENTS, json_encode($orderCache));
    }
}
