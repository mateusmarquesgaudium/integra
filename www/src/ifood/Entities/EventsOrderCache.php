<?php

namespace src\ifood\Entities;

use src\ifood\Enums\RedisSchema;
use src\geral\RedisService;

class EventsOrderCache
{
    private RedisService $redisClient;

    public function __construct(RedisService $redisClient)
    {
        $this->redisClient = $redisClient;
    }

    public function addEventInProcess(array $event): void
    {
        $this->redisClient->rPush(RedisSchema::KEY_LIST_ORDERS_EVENTS, json_encode($event));
    }
}