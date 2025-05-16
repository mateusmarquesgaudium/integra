<?php

use src\Aiqfome\Entities\AuthenticateStoreCache;
use src\Aiqfome\Enums\RedisSchema;
use src\geral\RedisService;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);
    $this->authenticate = new AuthenticateStoreCache($this->redisClient, [
        'access_token' => 'access_token',
        'expires_in' => 3600,
        'refresh_token' => 'refresh_token',
    ]);
});

test('verifica se o token foi salvo corretamente com HMSET', function () {
    $enterpriseId = 1;
    $key = str_replace('{enterpriseId}', $enterpriseId, RedisSchema::KEY_AUTHENTICATE_AIQFOME);
    $this->redisClient->expects($this->once())
        ->method('hMSet')
        ->with($key, [
            'access_token' => 'access_token',
            'expires_in' => 3600,
            'refresh_token' => 'refresh_token',
        ]);

    $this->authenticate->saveToken($key);
});
