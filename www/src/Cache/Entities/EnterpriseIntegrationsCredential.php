<?php

namespace src\Cache\Entities;

use ArrayAccess;
use src\Cache\Enums\CacheKeys;
use src\geral\RedisService;

class EnterpriseIntegrationsCredential extends EntityManager
{
    private RedisService $redisService;
    private int $enterpriseIntegrationId;
    private string $key;
    private string $value;

    public function __construct(RedisService $redisService)
    {
        $this->redisService = $redisService;
    }

    public function save(array $data): void
    {
        $this->setData($data);
        $this->redisService->hSet($this->getKey(), $this->key, $this->value);
        $this->setProviderMerchant();
    }

    public function update(array $data): void
    {
        $this->setData($data);
        $this->redisService->hSet($this->getKey(), $this->key, $this->value);
        $this->setProviderMerchant();
    }

    public function delete(array $data): void
    {
        $this->setData($data);
        $merchantId = $this->redisService->hGet($this->getKey(), 'merchant_id');
        $provider = $this->redisService->hGet($this->getKey(), 'provider');

        $this->redisService->del(str_replace(['{provider}', '{merchantId}'], [$provider, $merchantId], CacheKeys::PROVIDER_MERCHANTS_KEY));
        $this->redisService->hDel($this->getKey(), $this->key);
    }

    protected function getKey(): string
    {
        return str_replace('{integrationId}', $this->enterpriseIntegrationId, CacheKeys::ENTERPRISE_INTEGRATION_KEY);
    }

    protected function checkRulesForUpdate(): bool
    {
        return true;
    }

    protected function setData($data): void
    {
        $this->enterpriseIntegrationId = $data['empresa_integracao_id'];
        $this->key = $data['chave_credencial'] ?? null;
        $this->value = $data['valor_credencial'] ?? null;
    }

    protected function checkLastEventDateTime(): bool
    {
        return true;
    }

    private function setProviderMerchant(): void
    {
        if ($this->key !== 'merchant_id') {
            return;
        }

        $provider = $this->redisService->hGet($this->getKey(), 'provider');
        $enterpriseId = $this->redisService->hGet($this->getKey(), 'enterpriseId');
        $companyId = $this->redisService->hGet(
            str_replace('{enterpriseId}', $enterpriseId, CacheKeys::ENTERPRISE_KEY),
            'companyId'
        );

        $data = [
            'companyId' => $companyId,
            'enterpriseId' => $enterpriseId,
            'enterpriseIntegrationId' => $this->enterpriseIntegrationId
        ];

        $this->redisService->hMSet(
            str_replace(['{provider}', '{merchantId}'], [$provider, $this->value], CacheKeys::PROVIDER_MERCHANTS_KEY),
            [
                'companyId' => $companyId,
                'enterpriseId' => $enterpriseId,
                'enterpriseIntegrationId' => $this->enterpriseIntegrationId
            ]
        );
    }
}