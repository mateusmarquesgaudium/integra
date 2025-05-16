<?php

namespace src\Cache\Entities;

use src\Cache\Enums\CacheKeys;
use src\Cache\Enums\Provider;
use src\geral\RedisService;

class EnterpriseConfigurationIntegration extends EntityManager
{
    private RedisService $redisService;
    private int $enterpriseId;
    private string $provider;
    private bool $enabled;

    public function __construct(RedisService $redisService)
    {
        $this->redisService = $redisService;
    }

    public function save(array $data): void
    {
        $this->setData($data);
        if (!$this->checkLastEventDateTime()) {
            return;
        }

        $this->redisService->hSet($this->getKey(), $this->provider, $this->enabled);
    }

    public function update(array $data): void
    {
        $this->setData($data);
        if (!$this->checkLastEventDateTime()) {
            return;
        }

        if (!$this->checkRulesForUpdate()) {
            $this->redisService->hDel($this->getKey(), $this->provider);
            return;
        }

        $this->redisService->hSet($this->getKey(), $this->provider, $this->enabled);
    }

    public function delete(array $data): void
    {
        $this->setData($data);
        if (!$this->checkLastEventDateTime()) {
            return;
        }

        $this->redisService->hDel($this->getKey(), $this->provider);
    }

    protected function getKey(): string
    {
        return str_replace('{enterpriseId}', $this->enterpriseId, CacheKeys::ENTERPRISE_KEY);
    }

    protected function checkRulesForUpdate(): bool
    {
        return $this->enabled;
    }

    protected function setData($data): void
    {
        $this->enterpriseId = $data['empresa_id'] ?? null;
        $this->provider = Provider::getProviderName($data['integracao_provider_id'] ?? null);
        $this->enabled = $data['integracao_habilitada'] ?? null;
    }

    protected function checkLastEventDateTime(): bool
    {
        return true;
    }
}