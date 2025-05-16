<?php

namespace src\Aiqfome\Entities;

use src\geral\RedisService;

class AuthenticateStoreCache
{
    private RedisService $redisService;
    private array $data;
    private int $refreshTokenTime;

    public function __construct(RedisService $redisService, array $data)
    {
        $this->redisService = $redisService;
        $this->data = $data;
        $this->refreshTokenTime = $data['expires_in'];
    }

    public function saveToken(string $key): void
    {
        $this->redisService->hMSet($key, $this->data);
        $this->redisService->expire($key, $this->refreshTokenTime);
    }
}