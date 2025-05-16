<?php

namespace src\Aiqfome\Handlers;

use src\Delivery\Enums\Provider;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;

class CheckMerchantInProviderHandler
{
    private Request $request;
    private RedisService $redisService;

    public function __construct(Request $request, RedisService $redisService)
    {
        $this->request = $request;
        $this->redisService = $redisService;
    }

    public function execute(array $credentials, string $merchant): bool
    {
        $getAccessTokenHandler = new GetAccessTokenHandler($this->redisService);
        $accessToken = $getAccessTokenHandler->execute($merchant, Provider::AIQFOME);

        $response = $this->request
                        ->setRequestMethod('GET')
                        ->setHeaders([
                            'User-Agent: curl/7.68.0',
                            'Authorization: Bearer ' . $accessToken,
                            'Content-Type: application/json',
                            'aiq-client-authorization: ' . $credentials['aiq-client-authorization'],
                            'aiq-user-agent: ' . $credentials['aiq-user-agent'],
                        ])
                        ->execute();

        return $response->http_code === RequestConstants::HTTP_OK;
    }
}