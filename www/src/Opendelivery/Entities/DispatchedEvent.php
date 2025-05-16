<?php

namespace src\Opendelivery\Entities;

use DateTime;
use DateTimeZone;
use src\Delivery\Enums\OrderStatus;
use src\Delivery\Enums\RedisSchema;
use src\geral\RedisService;

class DispatchedEvent extends EventBase
{
    private RedisService $redisService;
    private string $provider;

    public function __construct(RedisService $redisService, string $provider)
    {
        $this->redisService = $redisService;
        $this->provider = $provider;
    }

    public function execute(array $event): void
    {
        $eventWebhook = [
            'provider' => $this->provider,
            'order_id' => $event['orderId'],
            'order_status' => OrderStatus::IN_TRANSIT,
            'event_created_at' => (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
        ];

        $this->redisService->rPush(RedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, json_encode($eventWebhook));
    }

}
