<?php

namespace src\Opendelivery\Handlers;

use src\geral\Custom;
use src\geral\CustomException;
use src\geral\Enums\RequestConstants;
use src\geral\JWTHandler;
use src\geral\RedisService;
use src\geral\Request;
use src\Opendelivery\Enums\RedisSchema;
use src\Opendelivery\Enums\Variables;

class OauthHandler
{
    private Custom $custom;
    private JWTHandler $jwtHandler;
    private RedisService $redisClient;
    private string $clientId;
    private string $clientSecret;

    public function __construct(RedisService $redisClient, string $clientId, string $clientSecret)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;

        $this->custom = new Custom();
        $this->jwtHandler = new JWTHandler($this->custom->getOpenDelivery()['secret']);
        $this->redisClient = $redisClient;
    }

    public function authenticateUser(): string
    {
        if (empty($this->clientId)) {
            throw new CustomException(
                json_encode([
                    'type' => Variables::TYPE_INVALID,
                    'message' => 'Invalid client_id in Authorization header'
                ]),
                400
            );
        }

        if (empty($this->clientSecret) || !$this->jwtHandler->isValid($this->clientSecret)) {
            throw new CustomException(
                json_encode([
                    'type' => Variables::TYPE_INVALID,
                    'message' => 'Invalid credentials for authorization'
                ]),
                401
            );
        }

        $payloadSecret = $this->jwtHandler->decode($this->clientSecret);
        if (empty($payloadSecret['empresa_id']) || empty($payloadSecret['provider'])) {
            throw new CustomException(
                json_encode([
                    'type' => Variables::TYPE_INVALID,
                    'message' => 'Invalid credentials for authorization'
                ]),
                401
            );
        }

        $key = str_replace(
            ['{provider}', '{empresa_id}', '{client_id}'],
            [$payloadSecret['provider'], $payloadSecret['empresa_id'], $this->clientId],
            RedisSchema::KEY_CREDENTIALS_PROVIDER
        );
        $companyCredentials = json_decode($this->redisClient->get($key), true);

        if (empty($companyCredentials)) {
            $companyCredentials = $this->checkCredentialsInMonolith($payloadSecret, $key);
        }

        if (empty($companyCredentials) || $this->clientSecret != $companyCredentials['client_secret'] || $this->clientId != $companyCredentials['client_id']) {
            throw new CustomException(
                json_encode([
                    'type' => Variables::TYPE_INVALID,
                    'message' => 'Invalid credentials for authorization',
                ]),
                401
            );
        }

        $payload = [
            'provider' => $payloadSecret['provider']
        ];

        $accessToken = $this->jwtHandler->encode($payload);
        $this->redisClient->set(RedisSchema::KEY_AUTHENTICATE_ORDER_SERVICE . $accessToken, 1);
        $this->redisClient->expire(RedisSchema::KEY_AUTHENTICATE_ORDER_SERVICE . $accessToken, RedisSchema::TTL_AUTHENTICATE_ORDER_SERVICE);

        return $accessToken;
    }

    private function checkCredentialsInMonolith(array $payloadSecret, string $key): array
    {
        $request = new Request($this->custom->getOpenDelivery()['url_verify_credentials']);
        $data = [
            'client_secret' => $this->clientSecret,
            'provider' => $payloadSecret['provider'],
            'empresa_id' => $payloadSecret['empresa_id'],
        ];
        $signature = hash_hmac('sha256', json_encode($data), $this->custom->getOpenDelivery()['signature']);
        $response = $request
            ->setHeaders(['Content-Type: application/json', 'x-integra-signature: ' . $signature])
            ->setRequestMethod('POST')
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields($data)
            ->execute();

        if ($response?->http_code != 200) {
            throw new CustomException(
                json_encode([
                    'type' => Variables::TYPE_INVALID,
                    'message' => 'Invalid credentials for authorization',
                ]),
                401
            );
        }

        $content = json_decode($response?->content ?? '', true);
        $this->redisClient->set($key, json_encode($content));
        $this->redisClient->expire($key, RedisSchema::TTL_AUTHENTICATE_ORDER_SERVICE);

        if ($content['status'] != 'success') {
            throw new CustomException(
                json_encode([
                    'type' => Variables::TYPE_INVALID,
                    'message' => 'Invalid credentials for authorization'
                ]),
                401
            );
        }

        return $content;
    }
}