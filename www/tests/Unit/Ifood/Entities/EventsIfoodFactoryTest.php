<?php

use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\ifood\Entities\EventsIfoodFactory;
use src\ifood\Enums\DeliveryStatus;
use src\ifood\Enums\RedisSchema;
use src\ifood\Http\Oauth;
use Tests\Helpers\CacheHelper;
use Tests\Helpers\MockHelpers\RequestMockHelper;
use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = Mockery::mock(RedisService::class);
    $this->oauth = $this->createMock(Oauth::class);
    $this->oauth->accessToken = fake()->uuid();
    $this->orderId = fake()->uuid();
});

test('entidade não encontrada', function () {
    EventsIfoodFactory::create('not_found', $this->oauth, $this->redisClient);
})->throws(\Exception::class, 'Entity not found');

test('taxa excede o limite (ratelimit)', function (string $entity) {
    CacheHelper::mockRedisClientMethod($this->redisClient, 'zRemRangeByScore', null, 1, 1);
    CacheHelper::mockRedisClientMethod($this->redisClient, 'zCard', null, 3001, 1);

    $eventIfoodFactory = EventsIfoodFactory::create($entity, $this->oauth, $this->redisClient);
    $result = $eventIfoodFactory->send([]);
    expect($result)->toBeFalse();
})->with(['ASSIGN_DRIVER', 'GOING_TO_ORIGIN', 'ARRIVED_AT_ORIGIN', 'DISPATCHED', 'ARRIVED_AT_DESTINATION']);

test('envio dos eventos', function (string $entity, int $httpCode, string $webhookType, bool $expected, ?string $lastEventProcessed, ?bool $failTransition = false) {
    CacheHelper::mockRedisClientMethod($this->redisClient, 'zRemRangeByScore', null, 1, 1);
    CacheHelper::mockRedisClientMethod($this->redisClient, 'zCard', null, 20, 1);
    CacheHelper::mockRedisClientMethod($this->redisClient, 'zAdd', null, 1, 1);

    $lastEventProcessedKey = CacheHelper::createCacheKey(RedisSchema::KEY_LAST_EVENT_PROCESSED, ['{order_id}' => $this->orderId]);
    CacheHelper::mockRedisClientMethod($this->redisClient, 'get', $lastEventProcessedKey, $lastEventProcessed, 1);

    if ($entity != 'ASSIGN_DRIVER') {
        $keyEventRetry = CacheHelper::createCacheKey(RedisSchema::KEY_RETRY_ORDER_EVENTS, ['{order_id}' => $this->orderId, '{event_type}' => $webhookType]);
        CacheHelper::mockRedisClientMethod($this->redisClient, 'get', $keyEventRetry, 0, 1);

        if (!$failTransition) {
            CacheHelper::mockRedisClientMethod($this->redisClient, 'del', $keyEventRetry, 1, 1);
        } else {
            CacheHelper::mockRedisClientMethod($this->redisClient, 'incr', $keyEventRetry, 1, 1);
            CacheHelper::mockRedisClientMethod($this->redisClient, 'expire', [$keyEventRetry, 1800], 1, 1);
        }
    }

    if (!empty($webhookType) && !empty($lastEventProcessedKey) && !$failTransition) {
        CacheHelper::mockRedisClientMethod($this->redisClient, 'set', [$lastEventProcessedKey, $webhookType], 1, 1);
        CacheHelper::mockRedisClientMethod($this->redisClient, 'expire', [$lastEventProcessedKey, 21600], 1, 1);
    }

    RequestMockHelper::createExternalMock($httpCode, []);

    $eventData = [
        'orderId' => $this->orderId,
        'webhookType' => $webhookType,
        'workerName' => fake()->name(),
        'workerPhone' => fake()->phoneNumber(),
        'workerVehicleType' => 'MOTORCYCLE',
    ];
    $eventIfoodFactory = EventsIfoodFactory::create($entity, $this->oauth, $this->redisClient);
    $result = $eventIfoodFactory->send($eventData);
    expect($result)->toBe($expected);
})->with([
    'ASSIGN_DRIVER -> webhookType vazio' => ['ASSIGN_DRIVER', RequestConstants::HTTP_OK, '', false, null],
    'ASSIGN_DRIVER -> HTTP_OK' => ['ASSIGN_DRIVER', RequestConstants::HTTP_OK, DeliveryStatus::ASSIGN_DRIVER, true, null],
    'GOING_TO_ORIGIN -> HTTP_OK' => ['GOING_TO_ORIGIN', RequestConstants::HTTP_OK, DeliveryStatus::GOING_TO_ORIGIN, true, DeliveryStatus::ASSIGN_DRIVER],
    'ARRIVED_AT_ORIGIN -> HTTP_OK' => ['ARRIVED_AT_ORIGIN', RequestConstants::HTTP_OK, DeliveryStatus::ARRIVED_AT_ORIGIN, true, DeliveryStatus::GOING_TO_ORIGIN],
    'DISPATCHED -> HTTP_OK' => ['DISPATCHED', RequestConstants::HTTP_OK, DeliveryStatus::DISPATCHED, true, DeliveryStatus::ARRIVED_AT_ORIGIN],
    'ARRIVED_AT_DESTINATION -> HTTP_OK' => ['ARRIVED_AT_DESTINATION', RequestConstants::HTTP_OK, DeliveryStatus::ARRIVED_AT_DESTINATION, true, DeliveryStatus::DISPATCHED],

    'ASSIGN_DRIVER -> HTTP_FORBIDDEN' => ['ASSIGN_DRIVER', RequestConstants::HTTP_FORBIDDEN, DeliveryStatus::ASSIGN_DRIVER, false, null],
    'GOING_TO_ORIGIN -> HTTP_FORBIDDEN' => ['GOING_TO_ORIGIN', RequestConstants::HTTP_FORBIDDEN, DeliveryStatus::GOING_TO_ORIGIN, false, DeliveryStatus::ASSIGN_DRIVER],
    'ARRIVED_AT_ORIGIN -> HTTP_FORBIDDEN' => ['ARRIVED_AT_ORIGIN', RequestConstants::HTTP_FORBIDDEN, DeliveryStatus::ARRIVED_AT_ORIGIN, false, DeliveryStatus::GOING_TO_ORIGIN],
    'DISPATCHED -> HTTP_FORBIDDEN' => ['DISPATCHED', RequestConstants::HTTP_FORBIDDEN, DeliveryStatus::DISPATCHED, false, DeliveryStatus::ARRIVED_AT_ORIGIN],
    'ARRIVED_AT_DESTINATION -> HTTP_FORBIDDEN' => ['ARRIVED_AT_DESTINATION', RequestConstants::HTTP_FORBIDDEN, DeliveryStatus::ARRIVED_AT_DESTINATION, false, DeliveryStatus::DISPATCHED],

    'GOING_TO_ORIGIN -> transição inválida' => ['GOING_TO_ORIGIN', RequestConstants::HTTP_OK, DeliveryStatus::GOING_TO_ORIGIN, false, DeliveryStatus::GOING_TO_ORIGIN, true],
    'ARRIVED_AT_ORIGIN -> transição inválida' => ['ARRIVED_AT_ORIGIN', RequestConstants::HTTP_OK, DeliveryStatus::ARRIVED_AT_ORIGIN, false, DeliveryStatus::ARRIVED_AT_ORIGIN, true],
    'DISPATCHED -> transição inválida' => ['DISPATCHED', RequestConstants::HTTP_OK, DeliveryStatus::DISPATCHED, false, DeliveryStatus::DISPATCHED, true],
    'ARRIVED_AT_DESTINATION -> transição inválida' => ['ARRIVED_AT_DESTINATION', RequestConstants::HTTP_OK, DeliveryStatus::ARRIVED_AT_DESTINATION, false, DeliveryStatus::ARRIVED_AT_DESTINATION, true],
]);

