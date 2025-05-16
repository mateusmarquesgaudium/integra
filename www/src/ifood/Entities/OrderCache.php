<?php

namespace src\ifood\Entities;

use DateTime;
use DateTimeZone;
use MchLog;
use src\Delivery\Enums\EventWebhookType;
use src\Delivery\Enums\OrderStatus;
use src\Delivery\Enums\Provider;
use src\Delivery\Enums\RedisSchema as DeliveryRedisSchema;
use src\ifood\Enums\RedisSchema;
use src\geral\RedisService;
use src\Cache\Entities\CacheValidator;

class OrderCache
{
    private RedisService $redisClient;
    private int $secondsToExpirePending = 60;

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
            'provider' => Provider::IFOOD,
            'order_id' => $orderId,
            'key_order_details' => str_replace('{order_id}', $orderId, RedisSchema::KEY_ORDER_DETAILS),
        ];

        $useWebhook = $this->redisClient->get(RedisSchema::KEY_ENABLE_WEBHOOK);
        if (empty($useWebhook)) {
            return;
        }

        // Verifica se merchantid existe no redis, se não dá um return
        $validaCache = new CacheValidator($this->redisClient, Provider::IFOOD, $merchantId);
        if (!$validaCache->validateCache()) {
            return;
        }

        if ($orderStatus == EventWebhookType::IFOOD_CONFIRMED) {
            $orderCache['order_status'] = OrderStatus::APPROVED;
            $this->redisClient->rPush(RedisSchema::KEY_LIST_APPROVED_ORDER_EVENTS, json_encode($orderCache));
            return;
        } elseif ($orderStatus == EventWebhookType::IFOOD_CANCELLED) {
            $orderCache['order_status'] = OrderStatus::HIDDEN;
        } elseif ($orderStatus == EventWebhookType::IFOOD_DISPATCHED) {
            $orderCache['order_status'] = OrderStatus::IN_TRANSIT;
        }

        $orderDetails = $this->redisClient->get($orderCache['key_order_details']);
        if (!$orderDetails) {
            $keyOrderPending = str_replace('{order_id}', $orderId, RedisSchema::KEY_LIST_PENDING_ORDER_EVENTS);

            $this->redisClient->rPush($keyOrderPending, json_encode($orderCache));
            $this->redisClient->expire($keyOrderPending, $this->secondsToExpirePending);
            return;
        }

        $this->redisClient->rPush(DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, json_encode($orderCache));
    }

    public function clearOrderWebhookCache(string $orderId): void
    {
        $pipeline = [];

        $pipeline[] = ['del', str_replace('{order_id}', $orderId, RedisSchema::KEY_LAST_EVENT_PROCESSED)];
        $pipeline[] = ['del', str_replace('{order_id}', $orderId, RedisSchema::KEY_ORDER_DETAILS)];

        $this->redisClient->pipelineCommands($pipeline);
    }
}