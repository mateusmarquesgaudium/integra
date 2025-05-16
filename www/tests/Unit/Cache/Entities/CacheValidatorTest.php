<?php

use src\Cache\Entities\CacheValidator;
use src\Cache\Enums\CacheKeys;
use src\Cache\Enums\StatusEntity;
use src\Delivery\Enums\Provider;

use function Pest\Faker\fake;

beforeEach(function () {
    /** @var RedisService */
    $this->redisService = Mockery::mock('src\geral\RedisService');
    $this->merchantId = fake()->uuid();
    $this->cacheValidator = new CacheValidator($this->redisService, Provider::AIQFOME, $this->merchantId);

    $this->providerMerchantValues = [
        'companyId' => '1',
        'enterpriseId' => '2',
        'enterpriseIntegrationId' => '3',
    ];

    $this->providerMerchantsKey = str_replace(
        ['{provider}', '{merchantId}'],
        [Provider::AIQFOME, $this->merchantId],
        cacheKeys::PROVIDER_MERCHANTS_KEY
    );
});

test('verifica se existe a chave do merchant no provider', function() {
    $this->redisService->shouldReceive('exists')->once()->with($this->providerMerchantsKey)->andReturn(false);
    $result = $this->cacheValidator->validateCache();
    expect($result)->toBeFalse();
});

test('verifica se a central não está ativa', function() {
    $this->redisService->shouldReceive('exists')->once()->with($this->providerMerchantsKey)->andReturn(true);
    $this->redisService->shouldReceive('hGet')->once()->with($this->providerMerchantsKey, 'companyId')->andReturn($this->providerMerchantValues['companyId']);

    $companyKey = str_replace('{companyId}', $this->providerMerchantValues['companyId'], CacheKeys::COMPANY_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($companyKey, 'state')->andReturn(StatusEntity::SUSPENDED);

    $result = $this->cacheValidator->validateCache();
    expect($result)->toBeFalse();
});

test('verifica se a empresa não está ativa', function() {
    $this->redisService->shouldReceive('exists')->once()->with($this->providerMerchantsKey)->andReturn(true);
    $this->redisService->shouldReceive('hGet')->once()->with($this->providerMerchantsKey, 'companyId')->andReturn($this->providerMerchantValues['companyId']);
    $this->redisService->shouldReceive('hGet')->once()->with($this->providerMerchantsKey, 'enterpriseId')->andReturn($this->providerMerchantValues['enterpriseId']);

    $companyKey = str_replace('{companyId}', $this->providerMerchantValues['companyId'], CacheKeys::COMPANY_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($companyKey, 'state')->andReturn(StatusEntity::ACTIVE);

    $enterpriseKey = str_replace('{enterpriseId}', $this->providerMerchantValues['enterpriseId'], CacheKeys::ENTERPRISE_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($enterpriseKey, 'state')->andReturn(StatusEntity::SUSPENDED);

    $result = $this->cacheValidator->validateCache();
    expect($result)->toBeFalse();
});

test('verifica se a integração na empresa não está ativa', function() {
    $this->redisService->shouldReceive('exists')->once()->with($this->providerMerchantsKey)->andReturn(true);
    $this->redisService->shouldReceive('hGet')->once()->with($this->providerMerchantsKey, 'companyId')->andReturn($this->providerMerchantValues['companyId']);
    $this->redisService->shouldReceive('hGet')->once()->with($this->providerMerchantsKey, 'enterpriseId')->andReturn($this->providerMerchantValues['enterpriseId']);

    $companyKey = str_replace('{companyId}', $this->providerMerchantValues['companyId'], CacheKeys::COMPANY_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($companyKey, 'state')->andReturn(StatusEntity::ACTIVE);

    $enterpriseKey = str_replace('{enterpriseId}', $this->providerMerchantValues['enterpriseId'], CacheKeys::ENTERPRISE_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($enterpriseKey, 'state')->andReturn(StatusEntity::ACTIVE);

    $this->redisService->shouldReceive('hGet')->once()->with($enterpriseKey, Provider::AIQFOME)->andReturnNull();

    $result = $this->cacheValidator->validateCache();
    expect($result)->toBeFalse();
});

test('verifica se a integração não está ativa', function() {
    $this->redisService->shouldReceive('exists')->once()->with($this->providerMerchantsKey)->andReturn(true);
    $this->redisService->shouldReceive('hGet')->once()->with($this->providerMerchantsKey, 'companyId')->andReturn($this->providerMerchantValues['companyId']);
    $this->redisService->shouldReceive('hGet')->once()->with($this->providerMerchantsKey, 'enterpriseId')->andReturn($this->providerMerchantValues['enterpriseId']);
    $this->redisService->shouldReceive('hGet')->once()->with($this->providerMerchantsKey, 'enterpriseIntegrationId')->andReturn($this->providerMerchantValues['enterpriseIntegrationId']);

    $companyKey = str_replace('{companyId}', $this->providerMerchantValues['companyId'], CacheKeys::COMPANY_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($companyKey, 'state')->andReturn(StatusEntity::ACTIVE);

    $enterpriseKey = str_replace('{enterpriseId}', $this->providerMerchantValues['enterpriseId'], CacheKeys::ENTERPRISE_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($enterpriseKey, 'state')->andReturn(StatusEntity::ACTIVE);
    $this->redisService->shouldReceive('hGet')->once()->with($enterpriseKey, Provider::AIQFOME)->andReturn(1);

    $integrationKey = str_replace('{integrationId}', $this->providerMerchantValues['enterpriseIntegrationId'], CacheKeys::ENTERPRISE_INTEGRATION_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($integrationKey, 'state')->andReturn(StatusEntity::SUSPENDED);

    $result = $this->cacheValidator->validateCache();
    expect($result)->toBeFalse();
});

test('verifica se a integração não está com recebimento automático', function() {
    $this->redisService->shouldReceive('exists')->once()->with($this->providerMerchantsKey)->andReturn(true);
    $this->redisService->shouldReceive('hGet')->once()->with($this->providerMerchantsKey, 'companyId')->andReturn($this->providerMerchantValues['companyId']);
    $this->redisService->shouldReceive('hGet')->once()->with($this->providerMerchantsKey, 'enterpriseId')->andReturn($this->providerMerchantValues['enterpriseId']);
    $this->redisService->shouldReceive('hGet')->once()->with($this->providerMerchantsKey, 'enterpriseIntegrationId')->andReturn($this->providerMerchantValues['enterpriseIntegrationId']);

    $companyKey = str_replace('{companyId}', $this->providerMerchantValues['companyId'], CacheKeys::COMPANY_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($companyKey, 'state')->andReturn(StatusEntity::ACTIVE);

    $enterpriseKey = str_replace('{enterpriseId}', $this->providerMerchantValues['enterpriseId'], CacheKeys::ENTERPRISE_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($enterpriseKey, 'state')->andReturn(StatusEntity::ACTIVE);
    $this->redisService->shouldReceive('hGet')->once()->with($enterpriseKey, Provider::AIQFOME)->andReturn(1);

    $integrationKey = str_replace('{integrationId}', $this->providerMerchantValues['enterpriseIntegrationId'], CacheKeys::ENTERPRISE_INTEGRATION_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($integrationKey, 'state')->andReturn(StatusEntity::ACTIVE);
    $this->redisService->shouldReceive('hGet')->once()->with($integrationKey, 'automaticReceipt')->andReturnNull();

    $result = $this->cacheValidator->validateCache();
    expect($result)->toBeFalse();
});

test('verifica se a integração está ativa', function() {
    $this->redisService->shouldReceive('exists')->once()->with($this->providerMerchantsKey)->andReturn(true);
    $this->redisService->shouldReceive('hGet')->once()->with($this->providerMerchantsKey, 'companyId')->andReturn($this->providerMerchantValues['companyId']);
    $this->redisService->shouldReceive('hGet')->once()->with($this->providerMerchantsKey, 'enterpriseId')->andReturn($this->providerMerchantValues['enterpriseId']);
    $this->redisService->shouldReceive('hGet')->once()->with($this->providerMerchantsKey, 'enterpriseIntegrationId')->andReturn($this->providerMerchantValues['enterpriseIntegrationId']);

    $companyKey = str_replace('{companyId}', $this->providerMerchantValues['companyId'], CacheKeys::COMPANY_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($companyKey, 'state')->andReturn(StatusEntity::ACTIVE);

    $enterpriseKey = str_replace('{enterpriseId}', $this->providerMerchantValues['enterpriseId'], CacheKeys::ENTERPRISE_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($enterpriseKey, 'state')->andReturn(StatusEntity::ACTIVE);
    $this->redisService->shouldReceive('hGet')->once()->with($enterpriseKey, Provider::AIQFOME)->andReturn(1);

    $integrationKey = str_replace('{integrationId}', $this->providerMerchantValues['enterpriseIntegrationId'], CacheKeys::ENTERPRISE_INTEGRATION_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($integrationKey, 'state')->andReturn(StatusEntity::ACTIVE);
    $this->redisService->shouldReceive('hGet')->once()->with($integrationKey, 'automaticReceipt')->andReturn(1);

    $result = $this->cacheValidator->validateCache();
    expect($result)->toBeTrue();
});

