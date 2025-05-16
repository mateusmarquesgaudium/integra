<?php

namespace src\Cache\Entities;

use src\geral\RedisService;
use src\Cache\Enums\CacheKeys;
use src\Cache\Enums\StatusEntity;

class CacheValidator
{
    private RedisService $redisClient;
    private string $provider;
    private string $merchantId;

    public function __construct(RedisService $redisClient, string $provider, string $merchantId)
    {
        $this->merchantId = $merchantId;
        $this->redisClient = $redisClient;
        $this->provider = $provider;
    }

    public function validateCache(): bool
    {
        $providerMerchantsKey = str_replace(
            ['{provider}', '{merchantId}'],
            [$this->provider, $this->merchantId],
            CacheKeys::PROVIDER_MERCHANTS_KEY
        );

        if (!$this->redisClient->exists($providerMerchantsKey)) {
            return false;
        }

        $companyId = $this->redisClient->hGet($providerMerchantsKey, 'companyId');
        $companyKey = str_replace('{companyId}', $companyId ?? 0, CacheKeys::COMPANY_KEY);

        if ($this->redisClient->hGet($companyKey, 'state') != StatusEntity::ACTIVE) {
            return false;
        }

        $enterpriseId = $this->redisClient->hGet($providerMerchantsKey, 'enterpriseId');
        $enterpriseKey = str_replace('{enterpriseId}', $enterpriseId ?? 0, CacheKeys::ENTERPRISE_KEY);

        if ($this->redisClient->hGet($enterpriseKey, 'state') != StatusEntity::ACTIVE || !$this->redisClient->hGet($enterpriseKey, $this->provider)) {
            return false;
        }

        $enterpriseIntegrationId = $this->redisClient->hGet($providerMerchantsKey, 'enterpriseIntegrationId');
        $integrationKey = str_replace('{integrationId}', $enterpriseIntegrationId ?? 0, CacheKeys::ENTERPRISE_INTEGRATION_KEY);

        if ($this->redisClient->hGet($integrationKey, 'state') != StatusEntity::ACTIVE) {
            return false;
        }

        if ($this->redisClient->hGet($integrationKey, 'automaticReceipt') != '1') {
            return false;
        }

        return true;
    }
}
