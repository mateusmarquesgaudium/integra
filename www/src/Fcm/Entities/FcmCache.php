<?php

namespace src\Fcm\Entities;

use src\Fcm\Enums\RedisSchema;
use src\geral\RedisService;

class FcmCache
{
    private RedisService $redisClient;

    public function __construct(RedisService $redisClient)
    {
        $this->redisClient = $redisClient;
    }

    public function addEvents(array $eventsData): void
    {
        $this->redisClient->lPush(RedisSchema::KEY_LIST_EVENTS_FCM, json_encode($eventsData));
    }
}
