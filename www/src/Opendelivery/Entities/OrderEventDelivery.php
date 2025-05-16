<?php

namespace src\Opendelivery\Entities;

use src\geral\RedisService;
use src\Opendelivery\Enums\RedisSchema;

class OrderEventDelivery
{
    private RedisService $redisService;

    public function __construct(RedisService $redisService)
    {
        $this->redisService = $redisService;
    }

    public function addOrderEventToCache(array $data)
    {
        if (empty($data['event'])) {
            return;
        }

        $this->redisService->rPush(RedisSchema::KEY_ORDERS_EVENTS, json_encode($data['event']));
    }
}
