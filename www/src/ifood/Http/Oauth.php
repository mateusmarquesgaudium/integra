<?php

namespace src\ifood\Http;

use src\ifood\Enums\RedisSchema;
use src\geral\Custom;
use src\geral\RedisService;
use src\geral\Request;
use InvalidArgumentException;
use src\geral\Enums\RequestConstants;
use src\ifood\Enums\OauthMode;
use UnexpectedValueException;

class Oauth
{
    private array $customIfood;
    public string $accessToken;
    private string $oauthMode;
    private RedisService $redisClient;

    private int $marginInSecondsForAcessTokenSafety = 300;

    public function __construct(RedisService $redisClient, string $oauthMode)
    {
        $this->redisClient = $redisClient;
        $this->oauthMode = $oauthMode;
        $this->customIfood = (new Custom())->getParams('ifood');
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
        // Verifica se no redis tem o token de acesso
        $keyOauthAccessToken = $this->oauthMode === OauthMode::POLLING ? RedisSchema::KEY_OAUTH_ACCESS_TOKEN : RedisSchema::KEY_OAUTH_WEBHOOK_ACCESS_TOKEN;
        $accessToken = $this->redisClient->get($keyOauthAccessToken);
        if ($accessToken && is_string($accessToken)) {
            return $accessToken;
        }

        $clientId = $this->oauthMode === OauthMode::POLLING ? $this->customIfood['client_id'] : $this->customIfood['webhook_client_id'];
        $clientSecret = $this->oauthMode === OauthMode::POLLING ? $this->customIfood['client_secret'] : $this->customIfood['webhook_client_secret'];

        // Gera um novo token de acesso através do OAuth do iFood
        $contentRequest = [
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'grantType' => $this->customIfood['grant_type'],
        ];

        $headersRequest = [
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $request = new Request($this->customIfood['uri'] . '/authentication/v1.0/oauth/token');
        $response = $request
            ->setRequestMethod('POST')
            ->setHeaders($headersRequest)
            ->setRequestType(RequestConstants::CURLOPT_POST_BUILD_QUERY)
            ->setPostFields($contentRequest)
            ->setSaveLogs(true)
            ->execute();
        if ($response->http_code != RequestConstants::HTTP_OK) {
            throw new UnexpectedValueException("[Auth] Unexpected HTTP response code: {$response->http_code}.");
        }

        $response->content = json_decode($response->content, true) ?: [];
        if (empty($response->content) || empty($response->content['accessToken']) || empty($response->content['expiresIn'])) {
            throw new InvalidArgumentException("[Auth] Access token or expiration time is missing in the response.");
        }

        $tokenExpiresIn = $response->content['expiresIn'] - $this->marginInSecondsForAcessTokenSafety;
        $this->redisClient->set($keyOauthAccessToken, $response->content['accessToken'], ['EX' => $tokenExpiresIn]);

        return $response->content['accessToken'];
    }
}