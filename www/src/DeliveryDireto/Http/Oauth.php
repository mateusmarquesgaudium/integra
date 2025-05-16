<?php

namespace src\DeliveryDireto\Http;

use src\DeliveryDireto\Enums\RedisSchema;
use src\geral\Custom;
use src\geral\RedisService;
use src\geral\Request;
use InvalidArgumentException;
use src\geral\Enums\RequestConstants;
use UnexpectedValueException;

class Oauth
{
    private array $customDeliveryDireto;
    private string $deliveryDiretoId;
    private string $username;
    private string $password;
    private string $accessToken;
    private RedisService $redisClient;

    private int $marginInSecondsForAcessTokenSafety = 60;

    public function __construct(RedisService $redisClient, string $deliveryDiretoId, string $username, string $password)
    {
        $this->redisClient = $redisClient;
        $this->deliveryDiretoId = $deliveryDiretoId;
        $this->username = $username;
        $this->password = $password;
        $this->customDeliveryDireto = (new Custom())->getParams('delivery_direto');
        $this->accessToken = $this->getAccessToken();
    }

    public function getHeadersRequests(): array
    {
        return [
            'Content-Type: application/json',
            "X-DeliveryDireto-ID: {$this->deliveryDiretoId}",
            "X-DeliveryDireto-Client-Id: {$this->customDeliveryDireto['client_id']}",
            "Authorization: Bearer {$this->accessToken}",
        ];
    }

    private function getAccessToken(): string
    {
        $hashRedis = md5($this->username . $this->password . $this->deliveryDiretoId);

        // Verifica se no redis tem o token de acesso
        $keyRedis = str_replace('{hash}', $hashRedis, RedisSchema::KEY_OAUTH_ACCESS_TOKEN);
        $accessToken = $this->redisClient->get($keyRedis);
        if ($accessToken && is_string($accessToken)) {
            return $accessToken;
        }

        // Gera um novo token de acesso através do OAuth do Delivery Direto
        $contentRequest = [
            'grant_type' => 'password',
            'client_id' => $this->customDeliveryDireto['client_id'],
            'client_secret' => $this->customDeliveryDireto['client_secret'],
            'username' => $this->username,
            'password' => $this->password,
        ];

        $headersRequest = [
            'Content-Type: application/json',
            "X-DeliveryDireto-ID: {$this->deliveryDiretoId}",
            "X-DeliveryDireto-Client-Id: {$this->customDeliveryDireto['client_id']}",
        ];

        $request = new Request($this->customDeliveryDireto['urlStoreAdminToken']);
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
        if (empty($response->content) || empty($response->content['access_token']) || empty($response->content['expires_in'])) {
            throw new InvalidArgumentException("[Auth] Access token or expiration time is missing in the response.");
        }

        $tokenExpiresIn = $response->content['expires_in'] - $this->marginInSecondsForAcessTokenSafety;
        $this->redisClient->set($keyRedis, $response->content['access_token'], ['EX' => $tokenExpiresIn]);

        return $response->content['access_token'];
    }
}