<?php

namespace src\AnotaAi\Http;

use src\AnotaAi\Enums\RedisSchema;
use src\geral\Custom;
use src\geral\RedisService;
use src\geral\Request;
use InvalidArgumentException;
use src\geral\Enums\RequestConstants;
use UnexpectedValueException;

class Oauth
{
    private array $customAnotaAi;
    private string $merchantId;
    private string $token;
    private string $accessToken;
    private RedisService $redisClient;

    private int $marginInSecondsForAcessTokenSafety = 60;

    public function __construct(RedisService $redisClient, string $merchantId, string $token)
    {
        $this->redisClient = $redisClient;
        $this->merchantId = $merchantId;
        $this->token = $token;
        $this->customAnotaAi = (new Custom())->getParams('anotaai');
        $this->accessToken = $this->getAccessToken();
    }

    public function getHeadersRequests(): array
    {
        return [
            'Content-Type: application/json',
            "Authorization: Bearer {$this->accessToken}",
        ];
    }

    private function getAccessToken(): string
    {
        $hashRedis = md5($this->merchantId . $this->token);

        // Verifica se no redis tem o token de acesso
        $keyRedis = str_replace('{hash}', $hashRedis, RedisSchema::KEY_OAUTH_ACCESS_TOKEN);
        $accessToken = $this->redisClient->get($keyRedis);
        if ($accessToken && is_string($accessToken)) {
            return $accessToken;
        }

        // Gera um novo token de acesso através do OAuth do Anota Ai
        $contentRequest = [
            'clientId' => $this->customAnotaAi['client_id'],
            'clientSecret' => $this->customAnotaAi['client_secret'],
        ];

        $headersRequest = [
            'Content-Type: application/json',
        ];

        $request = new Request($this->customAnotaAi['urlOauth']);
        $response = $request
            ->setRequestMethod('POST')
            ->setHeaders($headersRequest)
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields($contentRequest)
            ->setSaveLogs(true)
            ->execute();
        if ($response->http_code != RequestConstants::HTTP_OK) {
            throw new UnexpectedValueException("[Auth] Unexpected HTTP response code: {$response->http_code}.");
        }

        $response->content = json_decode($response->content, true) ?: [];
        if (empty($response->content) || empty($response->content['access_token']) || empty($response->content['expiresIn'])) {
            throw new InvalidArgumentException("[Auth] Access token or expiration time is missing in the response.");
        }

        $tokenExpiresIn = $response->content['expiresIn'] - $this->marginInSecondsForAcessTokenSafety;
        $this->redisClient->set($keyRedis, $response->content['access_token'], ['EX' => $tokenExpiresIn]);

        return $response->content['access_token'];
    }
}