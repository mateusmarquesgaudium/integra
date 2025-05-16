<?php

namespace src\Opendelivery\Entities;

use src\geral\RedisService;
use src\Opendelivery\Enums\Events;

class EventFactory
{
    private RedisService $redisService;
    private string $provider;
    private string $merchantId;

    public function __construct(RedisService $redisService, string $provider, string $merchantId)
    {
        $this->redisService = $redisService;
        $this->provider = $provider;
        $this->merchantId = $merchantId;
    }

    public function create(string $event): EventBase
    {
        switch ($event) {
            case Events::CONFIRMED:
                return new ConfirmedEvent($this->redisService);
            case Events::CANCELLED:
                return new CancelledEvent($this->redisService, $this->provider, $this->merchantId);
            case Events::DISPATCHED:
                return new DispatchedEvent($this->redisService, $this->provider);
            default:
                throw new \InvalidArgumentException('Invalid event type');
        }
    }
}
