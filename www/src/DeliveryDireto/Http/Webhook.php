<?php

namespace src\DeliveryDireto\Http;

use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\Request;

class Webhook
{
    private array $customDeliveryDireto;
    private Oauth $oauth;

    public function __construct(Oauth $oauth)
    {
        $this->oauth = $oauth;
        $this->customDeliveryDireto = (new Custom())->getParams('delivery_direto');
    }

    public function createWebhook(string $type, string $url): bool
    {
        $contentRequest = [
            'webhookUrl' => $url,
            'eventType' => $type,
            'status' => 'ACTIVE',
        ];

        $request = new Request($this->customDeliveryDireto['urlStoreAdmin'] . '/webhooks');
        $response = $request
            ->setRequestMethod('PUT')
            ->setHeaders($this->oauth->getHeadersRequests())
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields($contentRequest)
            ->setSaveLogs(true)
            ->execute();
        if ($response->http_code == RequestConstants::HTTP_OK) {
            return true;
        }
        return false;
    }

    public function deleteWebhook(string $type): bool
    {
        $request = new Request($this->customDeliveryDireto['urlStoreAdmin'] . "/webhooks/$type");
        $response = $request
            ->setRequestMethod('DELETE')
            ->setHeaders($this->oauth->getHeadersRequests())
            ->setSaveLogs(true)
            ->execute();
        if ($response->http_code == RequestConstants::HTTP_NO_CONTENT || $response->http_code == RequestConstants::HTTP_NOT_FOUND) {
            return true;
        }
        return false;
    }
}