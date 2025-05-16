<?php

namespace src\Opendelivery\Handlers;

require_once __DIR__ . '/../../vendor/autoload.php';

use src\geral\Custom;
use src\geral\CustomException;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use src\geral\RequestValidator;
use src\Opendelivery\Entities\OrderCache;
use src\Opendelivery\Enums\DeliveryStatus;

class LogisticsHandler
{
    private RedisService $redisService;
    private Custom $custom;
    private string $provider;
    private OrderCache $orderCache;

    public function __construct(RedisService $redisService, Custom $custom, string $provider)
    {
        $this->redisService = $redisService;
        $this->custom = $custom;
        $this->provider = $provider;
        $this->orderCache = new OrderCache($this->redisService, $this->provider);
    }

    public function getDelivery(): array
    {
        $validator = new RequestValidator(['orderId'], 'GET', RequestConstants::CURLOPT_POST_BUILD_QUERY);
        $validator->validateRequest();
        $requestData = $validator->getData();

        $custom = new Custom;
        $order = $this->orderCache->getOrderCache($requestData['orderId']);

        // Chamada para o backend
        $request = new Request($custom->getOpenDelivery()['url_get_order_details'] . '?order_id=' . $requestData['orderId']);
        $signature = hash_hmac('sha256', $requestData['orderId'], $this->custom->getOpenDelivery()['signature']);
        $response = $request
            ->setHeaders([
                'x-integra-signature: ' . $signature
            ])
            ->setRequestMethod('GET')
            ->execute();

        if (in_array($response->http_code, [RequestConstants::HTTP_NOT_FOUND, 0]) && empty($order)) {
            throw new CustomException('The requested resource was not found', 404);
        }

        if ($response->http_code == RequestConstants::HTTP_NOT_FOUND && $order['lastEvent'] == DeliveryStatus::PENDING) {
            return $order;
        }

        $orderResponse = json_decode($response->content, true);
        $orderResponse['events'] = $order['events'] ?? [];

        return $orderResponse;
    }
}