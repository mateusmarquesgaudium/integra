<?php

namespace src\Opendelivery\Handlers;

use src\Delivery\Entities\OrderInTransit as DeliveryOrderInTransit;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use src\geral\Util;
use src\Opendelivery\Service\ProviderCredentials;

class OrderInTransit extends DeliveryOrderInTransit
{
    private Custom $custom;
    private RedisService $redisService;

    public function __construct(RedisService $redisService, Custom $custom)
    {
        $this->redisService = $redisService;
        $this->custom = $custom;
    }

    public function send(array &$event): bool
    {
        if (!isset($event['orderId']) || !isset($event['provider']) || !isset($event['merchantId'])) {
            return false;
        }

        if (isset($event['timeToRetry']) && $event['timeToRetry'] > time()) {
            return false;
        }

        $providerCredentials = new ProviderCredentials($this->redisService, $this->custom, $event['provider']);
        $getAccessTokenHandler = new GetAccessTokenHandler(
            new Request($this->custom->getOpendelivery()['provider'][$event['provider']]['url'] . '/oauth/token'),
            $this->redisService,
            $event['provider'],
            $providerCredentials->getClientId($event['merchantId']),
            $providerCredentials->getClientSecret($event['merchantId']),
            $event['merchantId']
        );
        $accessToken = $getAccessTokenHandler->execute();

        if (empty($accessToken)) {
            return false;
        }

        $urlBase = $this->custom->getOpenDelivery()['provider'][$event['provider']]['url'];
        $url = $urlBase . "/v1/orders/{$event['orderId']}/dispatch";
        $request = new Request($url);

        $response = $request
            ->setRequestMethod('POST')
            ->setHeaders([
                "Authorization: Bearer {$accessToken}"
            ])
            ->setSaveLogs(true)
            ->execute();

        $httpCode = $response->http_code;

        if (in_array($httpCode, [RequestConstants::HTTP_UNAUTHORIZED, RequestConstants::HTTP_SERVICE_UNAVAILABLE])) {
            $event['nextMultiplierToRetry'] = isset($orderEvent['nextMultiplierToRetry']) ? $orderEvent['nextMultiplierToRetry'] * 2 : 1;
            $event['timeToRetry'] = Util::getNextTimeToRetry($event['nextMultiplierToRetry'], $this->custom->getOpendelivery()['timeToRetry']);
            return false;
        }

        return in_array($httpCode, [RequestConstants::HTTP_ACCEPTED, RequestConstants::HTTP_UNPROCESSABLE_ENTITY]);
    }
}
