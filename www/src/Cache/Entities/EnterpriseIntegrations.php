<?php

namespace src\Cache\Entities;

use src\Cache\Enums\CacheKeys;
use src\Cache\Enums\Provider;
use src\geral\RedisService;

class EnterpriseIntegrations extends EntityManager
{
    private RedisService $redisService;
    private int $id;
    private int $enterpriseId;
    private string $provider;
    private string $state;
    private int $automaticReceipt;
    private string $eventDateTime;

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

        $this->redisService->hMSet($this->getKey(), [
            'id' => $this->id,
            'enterpriseId' => $this->enterpriseId,
            'provider' => $this->provider,
            'state' => $this->state,
            'automaticReceipt' => $this->automaticReceipt,
            'eventDateTime' => $this->eventDateTime
        ]);
    }

    public function update(array $data): void
    {
        $this->setData($data);
        if (!$this->checkLastEventDateTime()) {
            return;
        }

        if (!$this->checkRulesForUpdate()) {
            $this->redisService->hSet($this->getKey(), 'automaticReceipt', 0);
            return;
        }

        $this->redisService->hMSet($this->getKey(), [
            'id' => $this->id,
            'enterpriseId' => $this->enterpriseId,
            'provider' => $this->provider,
            'state' => $this->state,
            'automaticReceipt' => $this->automaticReceipt,
            'eventDateTime' => $this->eventDateTime
        ]);
    }

    public function delete(array $data): void
    {
        $this->setData($data);
        if (!$this->checkLastEventDateTime()) {
            return;
        }

        $this->redisService->del($this->getKey());
    }

    protected function getKey(): string
    {
        return str_replace('{integrationId}', $this->id, CacheKeys::ENTERPRISE_INTEGRATION_KEY);
    }

    protected function checkRulesForUpdate(): bool
    {
        return $this->automaticReceipt;
    }

    protected function setData(array $data): void
    {
        $this->id = $data['id'] ?? null;
        $this->enterpriseId = $data['empresa_id'] ?? null;
        $this->provider = Provider::getProviderName($data['integracao_provider_id'] ?? 0);
        $this->state = $data['status_integracao'] ?? null;
        $this->automaticReceipt = $data['recebimento_automatico'] ?? null;
        $this->eventDateTime = $data['timestamp'] ?? null;
    }

    protected function checkLastEventDateTime(): bool
    {
        $lastEventDateTime = $this->redisService->hGet($this->getKey(), 'eventDateTime');
        return empty($lastEventDateTime) || $this->eventDateTime > $lastEventDateTime;
    }
}