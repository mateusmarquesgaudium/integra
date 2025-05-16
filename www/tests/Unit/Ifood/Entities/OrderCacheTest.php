<?php

use src\Cache\Enums\CacheKeys;
use src\Cache\Enums\Provider;
use src\Cache\Enums\StatusEntity;
use src\Delivery\Enums\EventWebhookType;
use src\Delivery\Enums\OrderStatus;
use src\Delivery\Enums\RedisSchema as DeliveryRedisSchema;
use src\geral\RedisService;
use src\ifood\Entities\OrderCache;
use src\ifood\Enums\RedisSchema;
use Tests\Helpers\CacheHelper;
use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = Mockery::mock(RedisService::class);
    $this->merchantId = fake()->uuid();
    $this->orderId = fake()->uuid();
    $this->provider = Provider::getProviderName(Provider::IFOOD);
    $this->companyId = fake()->randomNumber();
    $this->enterpriseId = fake()->randomNumber();
    $this->keyOrderDetails = CacheHelper::createCacheKey(RedisSchema::KEY_ORDER_DETAILS, ['{order_id}' => $this->orderId]);
    $this->keyOrderPending = CacheHelper::createCacheKey(RedisSchema::KEY_LIST_PENDING_ORDER_EVENTS, ['{order_id}' => $this->orderId]);

    // CacheValidator
    $this->providerMerchantsKey = CacheHelper::createCacheKey(CacheKeys::PROVIDER_MERCHANTS_KEY, [
        '{provider}' => $this->provider,
        '{merchantId}' => $this->merchantId,
    ]);
    $this->companyKey = CacheHelper::createCacheKey(CacheKeys::COMPANY_KEY, ['{companyId}' => $this->companyId]);
    $this->enterpriseKey = CacheHelper::createCacheKey(CacheKeys::ENTERPRISE_KEY, ['{enterpriseId}' => $this->enterpriseId]);
    $this->integrationKey = CacheHelper::createCacheKey(CacheKeys::ENTERPRISE_INTEGRATION_KEY, ['{integrationId}' => 1]);

    CacheHelper::mockRedisClientMethod($this->redisClient, 'hGet', [$this->providerMerchantsKey, 'companyId'], $this->companyId);
    CacheHelper::mockRedisClientMethod($this->redisClient, 'hGet', [$this->companyKey, 'state'], StatusEntity::ACTIVE);
    CacheHelper::mockRedisClientMethod($this->redisClient, 'hGet', [$this->providerMerchantsKey, 'enterpriseId'], $this->enterpriseId);
    CacheHelper::mockRedisClientMethod($this->redisClient, 'hGet', [$this->enterpriseKey, 'state'], StatusEntity::ACTIVE);
    CacheHelper::mockRedisClientMethod($this->redisClient, 'hGet', [$this->enterpriseKey, $this->provider], true);
    CacheHelper::mockRedisClientMethod($this->redisClient, 'hGet', [$this->providerMerchantsKey, 'enterpriseIntegrationId'], 1);
    CacheHelper::mockRedisClientMethod($this->redisClient, 'hGet', [$this->integrationKey, 'state'], StatusEntity::ACTIVE);
    CacheHelper::mockRedisClientMethod($this->redisClient, 'hGet', [$this->integrationKey, 'automaticReceipt'], '1');

    // OrderCache do iFood
    $this->orderCache = new OrderCache($this->redisClient);
});

afterEach(function () {
    Mockery::close();
});

describe('addEventOrderWebhook', function () {
    test('webhook desativado', function () {
        CacheHelper::mockRedisClientMethod($this->redisClient, 'exists', $this->providerMerchantsKey, true, 0);
        CacheHelper::mockRedisClientMethod($this->redisClient, 'get', RedisSchema::KEY_ENABLE_WEBHOOK, '0', 1);
        $this->orderCache->addEventOrderWebhook($this->merchantId, $this->orderId, EventWebhookType::IFOOD_CONFIRMED);
    });

    test('merchant não encontrado no cache', function () {
        CacheHelper::mockRedisClientMethod($this->redisClient, 'get', RedisSchema::KEY_ENABLE_WEBHOOK, '1', 1);
        CacheHelper::mockRedisClientMethod($this->redisClient, 'exists', $this->providerMerchantsKey, false, 1);
        $this->orderCache->addEventOrderWebhook($this->merchantId, $this->orderId, EventWebhookType::IFOOD_CONFIRMED);
    });

    test('status do pedido confirmado', function () {
        CacheHelper::mockRedisClientMethod($this->redisClient, 'get', RedisSchema::KEY_ENABLE_WEBHOOK, '1', $this->once());
        CacheHelper::mockRedisClientMethod($this->redisClient, 'exists', $this->providerMerchantsKey, true, $this->once());

        $this->redisClient->shouldReceive('rPush')
            ->withArgs(function($key, $value) {
                $decodedValue = json_decode($value, true);
                return $key === RedisSchema::KEY_LIST_APPROVED_ORDER_EVENTS &&
                    $decodedValue['order_status'] === OrderStatus::APPROVED;
            })->once();
        $this->orderCache->addEventOrderWebhook($this->merchantId, $this->orderId, EventWebhookType::IFOOD_CONFIRMED);
    });

    test('pedido com detalhes', function (string $eventWebhookType, string $orderStatus) {
        CacheHelper::mockRedisClientMethod($this->redisClient, 'get', RedisSchema::KEY_ENABLE_WEBHOOK, '1', 1);
        CacheHelper::mockRedisClientMethod($this->redisClient, 'exists', $this->providerMerchantsKey, true, 1);
        CacheHelper::mockRedisClientMethod($this->redisClient, 'get', $this->keyOrderDetails, true, 1);

        $this->redisClient->shouldReceive('rPush')
            ->withArgs(function($key, $value) use ($orderStatus) {
                $decodedValue = json_decode($value, true);
                return $key === DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK &&
                    $decodedValue['order_status'] === $orderStatus;
            })->once();
        $this->orderCache->addEventOrderWebhook($this->merchantId, $this->orderId, $eventWebhookType);
    })->with([
        'pedido cancelado' => [EventWebhookType::IFOOD_CANCELLED, OrderStatus::HIDDEN],
        'pedido despachado' => [EventWebhookType::IFOOD_DISPATCHED, OrderStatus::IN_TRANSIT],
    ]);

    test('pedido sem detalhes', function (string $eventWebhookType, string $orderStatus) {
        CacheHelper::mockRedisClientMethod($this->redisClient, 'get', RedisSchema::KEY_ENABLE_WEBHOOK, '1', 1);
        CacheHelper::mockRedisClientMethod($this->redisClient, 'exists', $this->providerMerchantsKey, true, 1);
        CacheHelper::mockRedisClientMethod($this->redisClient, 'get', $this->keyOrderDetails, null, 1);
        CacheHelper::mockRedisClientMethod($this->redisClient, 'expire', [$this->keyOrderPending, 60], true, 1);

        $this->redisClient->shouldReceive('rPush')
            ->withArgs(function($key, $value) use ($orderStatus) {
                $decodedValue = json_decode($value, true);
                return $key === $this->keyOrderPending &&
                    $decodedValue['order_status'] === $orderStatus;
            })->once();
        $this->orderCache->addEventOrderWebhook($this->merchantId, $this->orderId, $eventWebhookType);
    })->with([
        'pedido cancelado' => [EventWebhookType::IFOOD_CANCELLED, OrderStatus::HIDDEN],
        'pedido despachado' => [EventWebhookType::IFOOD_DISPATCHED, OrderStatus::IN_TRANSIT],
    ]);
});

describe('clearOrderWebhookCache', function () {
    test('limpar cache de eventos de pedidos', function () {
        $this->redisClient->shouldReceive('pipelineCommands')
            ->withArgs(function($commands) {
                return count($commands) === 2 &&
                    $commands[0][0] === 'del' &&
                    $commands[1][0] === 'del';
            })->once();

        $this->orderCache->clearOrderWebhookCache($this->orderId);
    });
});