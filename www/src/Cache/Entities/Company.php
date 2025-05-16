<?php

namespace src\Cache\Entities;

use src\Cache\Enums\CacheKeys;
use src\Cache\Enums\StatusEntity;
use src\geral\RedisService;

class Company extends EntityManager
{
    private RedisService $redisService;
    private int $id;
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

    protected function getKey(): string
    {
        return str_replace('{companyId}', $this->id, CacheKeys::COMPANY_KEY);
    }

    protected function checkRulesForUpdate(): bool
    {
        return $this->state !== StatusEntity::DELETED;
    }

    protected function checkLastEventDateTime(): bool
    {
        $eventTimestamp = $this->redisService->hGet($this->getKey(), 'eventDateTime');
        return empty($eventTimestamp) || $this->eventDateTime > $eventTimestamp;
    }

    protected function setData(array $data): void
    {
        $this->id = $data['id'];
        $this->state = $data['status_bandeira'];
        $this->eventDateTime = $data['timestamp'];
    }
}