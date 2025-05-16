<?php

namespace src\Opendelivery\Handlers;

use src\geral\Custom;
use src\geral\CustomException;
use src\geral\JWTHandler;
use src\geral\RedisService;
use src\Opendelivery\Enums\RedisSchema;

class AuthenticateHandler
{
    private RedisService $redisService;
    private Custom $custom;
    private string $authorization;
    private JWTHandler $jwtHandler;
    private string $provider;

    public function __construct(string $authorization, Custom $custom, RedisService $redisService)
    {
        $this->authorization = $authorization;
        $this->custom = $custom;
        $this->redisService = $redisService;
        $this->jwtHandler = new JWTHandler($this->custom->getOpenDelivery()['secret']);
    }

    public function validarToken(): void
    {
        if (empty($this->authorization)) {
            throw new CustomException('Invalid credentials', 401);
        }

        if (strpos($this->authorization, 'Bearer ') !== 0) {
            throw new CustomException('Invalid Authorization header format', 400);
        }

        $token = substr($this->authorization, 7);
        $keyExpired = $this->redisService->get(RedisSchema::KEY_AUTHENTICATE_ORDER_SERVICE . $token);
        if (empty($keyExpired) || !$this->jwtHandler->isValid($token)) {
            throw new CustomException('User no authenticated', 401);
        }

        $this->provider = $this->jwtHandler->lastDecoded['provider'];
    }

    public function getProvider(): string
    {
        return $this->provider;
    }
}