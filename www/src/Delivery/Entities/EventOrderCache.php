<?php

namespace src\Delivery\Entities;

use src\Delivery\Enums\RedisSchema;
use src\geral\RedisService;

class EventOrderCache
{
    private RedisService $redisClient;

    public function __construct(RedisService $redisClient)
    {
        $this->redisClient = $redisClient;
    }

    public function addEventInTransit(array $event): void
    {
        $this->redisClient->rPush(RedisSchema::KEY_LIST_ORDERS_EVENTS_IN_TRANSIT, json_encode($event));
    }

    public function addEventFinished(array $event): void
    {
        $this->redisClient->rPush(RedisSchema::KEY_LIST_ORDERS_EVENTS_FINISHED, json_encode($event));
    }
}