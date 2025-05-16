<?php

namespace src\Aiqfome\Handlers;

use src\Aiqfome\Enums\RedisSchema;
use src\Aiqfome\Http\AuthenticateToken;
use src\Aiqfome\Http\BaseAuthenticate;
use src\Aiqfome\Http\RefreshToken;
use src\Cache\Enums\CacheKeys;
use src\Delivery\Enums\Provider;
use src\geral\Custom;
use src\geral\RedisService;
use src\geral\Request;

class RefreshTokenHandler
{
    protected Custom $custom;
    protected RedisService $redisService;
    protected MerchantsRefreshTokenHandler $merchantsRefreshTokenHandler;

    public function __construct(Custom $custom, RedisService $redisService, MerchantsRefreshTokenHandler $merchantsRefreshTokenHandler)
    {
        $this->custom = $custom;
        $this->redisService = $redisService;
        $this->merchantsRefreshTokenHandler = $merchantsRefreshTokenHandler;
    }

    public function handle(): void
    {
        $merchants = $this->merchantsRefreshTokenHandler->getMerchantsForRefresh();
        $pipeline = [];
        $merchantsFromRemove = [];
        foreach ($merchants as $merchantId) {
            if (in_array($merchantId, $merchantsFromRemove)) {
                continue;
            }

            if ($this->merchantsRefreshTokenHandler->checkMerchantInInvalidCredentials($merchantId)) {
                $merchantsFromRemove[] = $merchantId;
                $pipeline[] = ['sRem', RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH, $merchantId];
                continue;
            }

            $merchantCredentialsKey = str_replace(['{provider}', '{merchantId}'], [Provider::AIQFOME, $merchantId], CacheKeys::PROVIDER_MERCHANTS_KEY);
            $enterpriseId = $this->getValueFromKey($merchantCredentialsKey, 'enterpriseId');
            if (!$enterpriseId) {
                $merchantsFromRemove[] = $merchantId;
                $pipeline[] = ['sRem', RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH, $merchantId];
                continue;
            }

            $enterpriseIntegrationId = $this->getValueFromKey($merchantCredentialsKey, 'enterpriseIntegrationId');
            if (!$enterpriseIntegrationId) {
                $merchantsFromRemove[] = $merchantId;
                $pipeline[] = ['sRem', RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH, $merchantId];
                continue;
            }

            $request = new Request($this->custom->getParams('aiqfome')['url'] . '/auth/token');
            $authenticateHandler = new AuthenticateToken($request, $this->custom, $this->redisService);

            $integrationMerchantKey = str_replace('{integrationId}', $enterpriseIntegrationId, CacheKeys::ENTERPRISE_INTEGRATION_KEY);
            $username = $this->getValueFromKey($integrationMerchantKey, 'client_username');
            $password = $this->getValueFromKey($integrationMerchantKey, 'client_password');
            if (!$username || !$password) {
                $merchantsFromRemove[] = $merchantId;
                $pipeline[] = ['sRem', RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH, $merchantId];
                continue;
            }

            try {
                $authenticateHandler->handle($username, $password, $enterpriseId);
                $merchantsFromRemove[] = $merchantId;
                $pipeline[] = ['sRem', RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH, $merchantId];

                $key = str_replace('{merchant_id}', $merchantId, RedisSchema::KEY_AUTHENTICATE_AIQFOME_ERR_REFRESH_MERCHANT);
                $pipeline[] = ['del', $key];
            } catch (\Exception $e) {
                $key = str_replace('{merchant_id}', $merchantId, RedisSchema::KEY_AUTHENTICATE_AIQFOME_ERR_REFRESH_MERCHANT);
                $pipeline[] = ['set', $key, $e->getMessage()];

                $countKey = str_replace('{merchant_id}', $merchantId, RedisSchema::KEY_AUTHENTICATE_AIQFOME_ERR_COUNT_REFRESH_MERCHANT);
                $pipeline[] = ['incr', $countKey];
                $countRefreshTokenErrs = (int) $this->redisService->get($countKey);
                if ($countRefreshTokenErrs >= $this->custom->getParams('aiqfome')['max_attempts_refresh_token']) {
                    $merchantsFromRemove[] = $merchantId;
                    $pipeline[] = ['sRem', RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH, $merchantId];
                    $pipeline[] = ['del', $key];
                    $pipeline[] = ['sAdd', RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS, $merchantId];
                    $pipeline[] = ['rPush', RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS_LIST, $merchantId];
                }
            }
        }

        $this->redisService->pipelineCommands($pipeline);
    }

    private function getValueFromKey($key, $field): string
    {
        return $this->redisService->hGet($key, $field);
    }
}