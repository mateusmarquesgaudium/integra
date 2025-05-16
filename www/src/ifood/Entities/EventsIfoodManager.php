<?php

namespace src\ifood\Entities;

use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\ifood\Enums\DeliveryStatus;

abstract class EventsIfoodManager
{
    protected array $listHttpCodeSuccessful = [
        RequestConstants::HTTP_OK,
        RequestConstants::HTTP_ACCEPTED,
    ];
    protected int $maxRetry = 5;
    //expira em 6h
    protected int $ttlLastEvent = 21600;
    //expira em 30 min
    protected int $ttlRetry = 1800;
    protected int $windowTime = 65;
    protected RedisService $redisClient;

    public function __construct(RedisService $redisClient)
    {
        $this->redisClient = $redisClient;
    }

    abstract public function send(array $data): bool;

    protected function checkRateLimit(string $keyEventRateLimit, int $maxRequests): bool
    {
        $currentTime = time();

        // Remover timestamps antigos
        $this->redisClient->zRemRangeByScore($keyEventRateLimit, 0, $currentTime - $this->windowTime);

        // Contar o número de requisições no intervalo atual
        $currentCount = $this->redisClient->zCard($keyEventRateLimit);

        if ($currentCount < $maxRequests) {
            // Ainda não atingiu o limite, então permite a requisição e registra o timestamp
            $this->redisClient->zAdd($keyEventRateLimit, $currentTime, $currentTime);
            return true;
        }
        return false;
    }

    protected function validateOrderEvent(string $webhookType, string $lastEventProcessedKey, string $keyEventRetry): bool
    {
        $lastEventProcessed = $this->redisClient->get($lastEventProcessedKey);

        if (empty($lastEventProcessed)) {
            if ($webhookType == DeliveryStatus::ASSIGN_DRIVER) {
                $this->redisClient->set($lastEventProcessedKey, $webhookType);
                $this->redisClient->expire($lastEventProcessedKey, $this->ttlLastEvent);
                return true;
            }
            return false;
        }

        $stateMachine = new OrderEventsStateMachine($lastEventProcessed);

        $retryCount = $this->redisClient->get($keyEventRetry) ?: 0;

        if ($retryCount > $this->maxRetry || $stateMachine->transition($webhookType)) {
            $this->redisClient->del($keyEventRetry);
            $this->redisClient->set($lastEventProcessedKey, $webhookType);
            $this->redisClient->expire($lastEventProcessedKey, $this->ttlLastEvent);
            return true;
        }

        $this->redisClient->incr($keyEventRetry);

        if ($retryCount == 0) {
            $this->redisClient->expire($keyEventRetry, $this->ttlRetry);
        }
        return false;
    }
}