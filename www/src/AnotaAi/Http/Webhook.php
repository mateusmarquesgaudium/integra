<?php

namespace src\AnotaAi\Http;

use src\Delivery\Enums\Provider;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\Request;
use src\geral\JWTHandler;

class Webhook
{
    private array $customAnotaAi;
    private Oauth $oauth;
    private JWTHandler $jwtHandler;

    public function __construct(Oauth $oauth)
    {
        $custom = new Custom();
        $this->oauth = $oauth;
        $this->customAnotaAi = $custom->getParams('anotaai');
        $this->jwtHandler = new JWTHandler($custom->getParams('delivery')['jwt_secret']);
    }

    public function createWebhook(string $merchantToken, string $url): bool|string
    {

        $contentRequest = [
            'merchant_token' => $merchantToken,
            'external_token' => $this->jwtHandler->encode(['provider' => Provider::ANOTAAI]),
            'order_accept' => [
                'url' => $url,
                'method' => 'POST'
            ],
            'order_cancel' => [
                'url' => $url,
                'method' => 'POST'
            ]
        ];

        $request = new Request($this->customAnotaAi['urlCreateWebhook']);
        $response = $request
            ->setRequestMethod('POST')
            ->setHeaders($this->oauth->getHeadersRequests())
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields($contentRequest)
            ->setSaveLogs(true)
            ->execute();
        if ($response->http_code != RequestConstants::HTTP_CREATED) {
            return false;
        }

        $responseData = json_decode($response->content, true);
        return $responseData['token'] ?? false;
    }
}