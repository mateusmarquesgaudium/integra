<?php

namespace src\Opendelivery\Service;

use src\Cache\Enums\CacheKeys;
use src\geral\Custom;
use src\geral\RedisService;

class ProviderCredentials
{
    private RedisService $redisService;
    private Custom $custom;
    private string $provider;

    public function __construct(RedisService $redisService, Custom $custom, string $provider)
    {
        $this->redisService = $redisService;
        $this->custom = $custom;
        $this->provider = $provider;
    }

    public function getClientId(string $merchantId) : string
    {
        if (empty($merchantId)) {
            return $this->custom->getOpenDelivery()['provider'][$this->provider]['client_id'];
        }

        $key = str_replace(['{provider}', '{merchantId}'], [$this->provider, $merchantId], CacheKeys::PROVIDER_MERCHANTS_KEY);
        $enterpriseIntegrationId = $this->redisService->hGet($key, 'enterpriseIntegrationId');
        if (empty($enterpriseIntegrationId)) {
            throw new \Exception('Enterprise Integration Id not found');
        }

        $key = str_replace('{integrationId}', $enterpriseIntegrationId, CacheKeys::ENTERPRISE_INTEGRATION_KEY);
        $clientId = $this->redisService->hGet($key, 'client_id');
        if (empty($clientId) && empty($this->custom->getOpenDelivery()['provider'][$this->provider]['client_id'])) {
            throw new \Exception('Client Id not found');
        } else if (empty($clientId)) {
            $clientId = $this->custom->getOpenDelivery()['provider'][$this->provider]['client_id'];
        }

        return $clientId;
    }

    public function getClientSecret(string $merchantId) : string
    {
        if (empty($merchantId)) {
            return $this->custom->getOpenDelivery()['provider'][$this->provider]['client_secret'];
        }

        $key = str_replace(['{provider}', '{merchantId}'], [$this->provider, $merchantId], CacheKeys::PROVIDER_MERCHANTS_KEY);
        $enterpriseIntegrationId = $this->redisService->hGet($key, 'enterpriseIntegrationId');
        if (empty($enterpriseIntegrationId)) {
            throw new \Exception('Enterprise Integration Id not found');
        }

        $key = str_replace('{integrationId}', $enterpriseIntegrationId, CacheKeys::ENTERPRISE_INTEGRATION_KEY);
        $clientSecret = $this->redisService->hGet($key, 'client_secret');
        if (empty($clientSecret) && empty($this->custom->getOpenDelivery()['provider'][$this->provider]['client_secret'])) {
            throw new \Exception('Client Secret not found');
        } else if (empty($clientSecret)) {
            $clientSecret = $this->custom->getOpenDelivery()['provider'][$this->provider]['client_secret'];
        }

        return $clientSecret;
    }
}
