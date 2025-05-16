<?php

namespace src\Aiqfome\Http;

use src\Aiqfome\Handlers\CheckMerchantInProviderHandler;
use src\Aiqfome\Handlers\GetAccessTokenHandler;
use src\Delivery\Enums\Provider;
use src\geral\Custom;
use src\geral\RedisService;
use src\geral\Request;
use src\geral\RequestMulti;

class DeleteIntegration
{
    private Custom $custom;
    private RedisService $redisService;
    private CheckMerchantInProviderHandler $checkMerchantInProvider;

    public function __construct(Custom $custom, RedisService $redisService, CheckMerchantInProviderHandler $checkMerchantInProvider)
    {
        $this->custom = $custom;
        $this->redisService = $redisService;
        $this->checkMerchantInProvider = $checkMerchantInProvider;
    }

    public function execute(string $merchantId, array $webhooks): void
    {
        $customProvider = $this->custom->getParams('aiqfome');

        $checkMerchant = $this->checkMerchantInProvider->execute($customProvider['credentials'], $merchantId);
        if (!$checkMerchant) {
            throw new \Exception('Merchant not found in provider');
        }

        $getAccessTokenHandler = new GetAccessTokenHandler($this->redisService);
        $accessToken = $getAccessTokenHandler->execute($merchantId, Provider::AIQFOME);

        $requests = [];
        foreach ($webhooks as $webhookId) {
            $requests[$webhookId] = $this->createRequest($merchantId, $customProvider, $webhookId, $accessToken, $customProvider['credentials']);
        }

        $requestMulti = new RequestMulti($requests);

        $requestMulti->execute();
    }

    private function createRequest(string $merchantId, array $customProvider, int $webhookId, string $accessToken, array $credentials): Request
    {
        $request = new Request($customProvider['url'] . '/store/' . $merchantId . '/webhooks/' . $webhookId);

        $request
            ->setRequestMethod('DELETE')
            ->setHeaders([
                'User-Agent: curl/7.68.0',
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'aiq-client-authorization: ' . $credentials['aiq-client-authorization'],
                'aiq-user-agent: ' . $credentials['aiq-user-agent'],
            ]);

        return $request;
    }
}