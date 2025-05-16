<?php

use src\geral\RedisService;
use src\ifood\Entities\EventsOrderCache;
use src\ifood\Enums\RedisSchema;
use Tests\Helpers\CacheHelper;

test('adiciona evento na lista de eventos', function () {
    $event = [
        'orderId' => 1,
    ];

    $redisClient = Mockery::mock(RedisService::class);
    CacheHelper::mockRedisClientMethod($redisClient, 'rPush', [RedisSchema::KEY_LIST_ORDERS_EVENTS, json_encode($event)], 1, 1);

    $eventsOrderCache = new EventsOrderCache($redisClient);
    $eventsOrderCache->addEventInProcess($event);
});