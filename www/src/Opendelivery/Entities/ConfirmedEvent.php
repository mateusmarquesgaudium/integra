<?php

namespace src\Opendelivery\Entities;

use src\geral\RedisService;
use src\Opendelivery\Enums\RedisSchema;

class ConfirmedEvent extends EventBase
{
    private RedisService $redisService;

    public function __construct(RedisService $redisService)
    {
        $this->redisService = $redisService;
    }

    public function execute(array $event): void
    {
        $this->redisService->rPush(RedisSchema::KEY_EVENTS_GET_DETAILS_ORDER, json_encode($event));
    }
}
