<?php

namespace src\Aiqfome\Handlers;

use src\Cache\Enums\CacheKeys;
use src\Aiqfome\Enums\RedisSchema;
use src\geral\RedisService;

class GetAccessTokenHandler
{
    private RedisService $redisService;

    public function __construct(RedisService $redisService)
    {
        $this->redisService = $redisService;
    }

    public function execute(string $merchantId, string $provider): string
    {
        $key = str_replace(['{provider}', '{merchantId}'], [$provider, $merchantId], CacheKeys::PROVIDER_MERCHANTS_KEY);
        $enterpriseId = $this->redisService->hGet($key, 'enterpriseId');

        if (!$enterpriseId) {
            throw new \Exception("Enterprise id not found for merchant {$merchantId}");
        }

        $key = str_replace('{enterprise_id}', $enterpriseId, RedisSchema::KEY_AUTHENTICATE_AIQFOME);
        $accessToken = $this->redisService->hGet($key, 'access_token');
        if (!$accessToken) {
            throw new \Exception("Access token not found for enterprise {$enterpriseId}");
        }

        return $accessToken;
    }
}