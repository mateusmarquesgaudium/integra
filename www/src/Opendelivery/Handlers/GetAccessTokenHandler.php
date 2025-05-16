<?php

namespace src\Opendelivery\Handlers;

use Exception;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use src\Opendelivery\Enums\RedisSchema;

class GetAccessTokenHandler
{
    private Request $request;
    private RedisService $redisService;
    private string $provider;
    private string $merchantId;
    private string $clientId;
    private string $clientSecret;

    public function __construct(Request $request, RedisService $redisService, string $provider, string $clientId, string $clientSecret, string $merchantId = '')
    {
        $this->request = $request;
        $this->redisService = $redisService;
        $this->provider = $provider;
        $this->merchantId = $merchantId;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public function execute() : string
    {
        $key = $this->getKey();
        $accessToken = $this->redisService->get($key);
        if (!empty($accessToken)) {
            return $accessToken;
        }

        $response = $this->request
            ->setRequestMethod('POST')
            ->setPostFields([
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
            ])
            ->setSaveLogs(true)
            ->execute();

        $responseContent = json_decode($response->content, true);
        $httpCode = $response->http_code;
        if ($httpCode !== RequestConstants::HTTP_OK || !isset($responseContent['access_token'])) {
            throw new Exception('Erro ao obter o access token');
        }

        $accessToken = $responseContent['access_token'];
        $this->redisService->set($key, $accessToken);
        $this->redisService->expire($key, $responseContent['expires_in']);

        return $accessToken;
    }

    private function getKey() : string
    {
        if (!empty($this->merchantId)) {
            return str_replace(['{provider}', '{merchantId}'], [$this->provider, $this->merchantId], RedisSchema::KEY_ACCESS_TOKEN_PROVIDER_MERCHANT);
        }

        return str_replace('{provider}', $this->provider, RedisSchema::KEY_ACCESS_TOKEN_PROVIDER);
    }
}
