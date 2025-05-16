<?php

namespace src\Opendelivery\Entities;

use src\geral\RedisService;
use src\Opendelivery\Enums\ProvidersOpenDelivery;
use src\Opendelivery\Enums\RedisSchema;
use src\Cache\Enums\CacheKeys;

class WebhookProvider {

    /**
     * @var RedisService
     */
    private  $redisService;

    public function __construct(RedisService $redisService) {
        $this->redisService = $redisService;
    }

    public function supportsMultipleWebhooks(string $provider): bool
    {
        if ($this->checkSupportInRedis($provider)) {
            return true;
        }

        // Lista de providers que suportam múltiplos webhooks
        $providersWithMultipleWebhooks = [
            ProvidersOpenDelivery::ECLETICA,
            ProvidersOpenDelivery::PROMOKIT
        ];

        return in_array(strtoupper($provider), $providersWithMultipleWebhooks, true);
    }

    public function getWebhookUrl(string $provider, string $merchantId): string
    {
        $key = str_replace(['{provider}', '{merchantId}'], [$provider, $merchantId], CacheKeys::PROVIDER_MERCHANTS_KEY);
        $enterpriseIntegration = $this->redisService->hGet($key, 'enterpriseIntegrationId');

        if (!$enterpriseIntegration) {
            throw new \Exception("Enterprise integration id not found for merchant {$merchantId}");
        }

        $key = str_replace('{integrationId}', $enterpriseIntegration, CacheKeys::ENTERPRISE_INTEGRATION_KEY);
        $url = $this->redisService->hGet($key, 'webhook_url');

        if (!$url) {
            throw new \Exception("Webhook url not found for enterprise integration {$enterpriseIntegration}");
        }

        return $url;
    }

    private function checkSupportInRedis(string $provider) : bool
    {
        $key = str_replace('{provider}', $provider, RedisSchema::KEY_SUPPORTS_MULTIPLE_WEBHOOKS);
        return $this->redisService->sIsMember($key, $provider);
    }
}