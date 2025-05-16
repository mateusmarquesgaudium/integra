<?php

namespace src\Cache\Entities;

use src\Cache\Enums\CacheKeys;
use src\Cache\Enums\StatusEntity;
use src\geral\RedisService;

class Enterprise extends EntityManager
{
    private RedisService $redisService;
    private int $id;
    private int $companyId;
    private string $state;
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
            'companyId' => $this->companyId,
            'state' => $this->state,
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
            $this->redisService->del($this->getKey());
            return;
        }

        $this->redisService->hMSet($this->getKey(), [
            'id' => $this->id,
            'companyId' => $this->companyId,
            'state' => $this->state,
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

    protected function setData($data): void
    {
        $this->id = $data['id'] ?? null;
        $this->companyId = $data['bandeira_id'] ?? null;
        $this->state = $data['status_empresa'] ?? null;
        $this->eventDateTime = $data['timestamp'] ?? null;
    }

    protected function checkLastEventDateTime(): bool
    {
        $eventTimestamp = $this->redisService->hGet($this->getKey(), 'eventDateTime');
        return empty($eventTimestamp) || $this->eventDateTime > $eventTimestamp;
    }

    protected function getKey(): string
    {
        return str_replace(['{companyId}', '{enterpriseId}'], [$this->companyId, $this->id], CacheKeys::ENTERPRISE_KEY);
    }

    protected function checkRulesForUpdate(): bool
    {
        return $this->state !== StatusEntity::DELETED;
    }
}