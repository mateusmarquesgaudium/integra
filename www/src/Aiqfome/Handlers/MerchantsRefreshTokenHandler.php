<?php

namespace src\Aiqfome\Handlers;

use src\Aiqfome\Enums\RedisSchema;
use src\geral\Custom;
use src\geral\RedisService;
use UnderflowException;

class MerchantsRefreshTokenHandler
{
    protected RedisService $redisService;

    public function __construct(RedisService $redisService)
    {
        $this->redisService = $redisService;
    }

    public function getMerchantsForRefresh(): array
    {
        $merchants = $this->redisService->sMembers(RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH);
        if (empty($merchants)) {
            throw new UnderflowException('Não há empresas para atualizar o token de autenticação.');
        }

        return $merchants;
    }

    public function checkMerchantInQueueForRefresh(string $merchantId): bool
    {
        return $this->isMerchantInSet($merchantId, RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH);
    }

    public function checkMerchantInInvalidCredentials(string $merchantId): bool
    {
        return $this->isMerchantInSet($merchantId, RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS);
    }

    public function getMerchantsForDesactivateIntegration(int $maxItens): array
    {
        $merchants = $this->redisService->lRange(RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS_LIST, 0, $maxItens);
        if (empty($merchants)) {
            throw new UnderflowException('Não há empresas para desativar a integração.');
        }

        return $merchants;
    }

    public function removeMerchantsForDesactivateIntegration(int $qtMerchants): bool
    {
        return $this->redisService->lTrim(RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS_LIST, $qtMerchants, -1);
    }

    private function isMerchantInSet(string $merchantId, string $setKey): bool
    {
        return $this->redisService->sIsMember($setKey, $merchantId);
    }
}