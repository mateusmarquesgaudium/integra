<?php

namespace src\DeliveryDireto\Entities;

use DateTime;
use DateTimeZone;
use src\Delivery\Enums\OrderStatus;
use src\Delivery\Enums\Provider;
use src\Delivery\Enums\RedisSchema as DeliveryRedisSchema;
use src\DeliveryDireto\Enums\RedisSchema;
use src\geral\RedisService;

class OrderCache
{
    private RedisService $redisClient;

    public function __construct(RedisService $redisClient)
    {
        $this->redisClient = $redisClient;
    }

    public function addEventOrderWebhook(string $merchantId, string $orderId, string $orderStatus): void
    {
        $dateUtc = new DateTime();
        $dateUtc->setTimezone(new DateTimeZone('UTC'));

        $orderCache = [
            'merchant_id' => $merchantId,
            'event_created_at' => $dateUtc->format('Y-m-d\TH:i:s\Z'),
            'provider' => Provider::DELIVERY_DIRETO,
            'order_id' => $orderId,
            'order_status' => $orderStatus,
            'key_order_details' => str_replace('{order_id}', $orderId, RedisSchema::KEY_ORDER_DETAILS),
        ];

        if ($orderStatus == OrderStatus::APPROVED) {
            $this->redisClient->rPush(RedisSchema::KEY_LIST_APPROVED_ORDER_EVENTS, json_encode($orderCache));
            return;
        }

        $orderDetails = $this->redisClient->get($orderCache['key_order_details']);
        if (!$orderDetails) {
            $keyOrderPending = str_replace('{order_id}', $orderId, RedisSchema::KEY_LIST_PENDING_ORDER_EVENTS);
            $this->redisClient->rPush($keyOrderPending, json_encode($orderCache));
            return;
        }

        $this->redisClient->rPush(DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, json_encode($orderCache));
    }
}