<?php

namespace src\Neemo\Http;

use src\Neemo\Enums\RedisSchema;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use src\Neemo\Enums\OrderStatus;

class OrderInTransit
{
    private array $customNeemo;
    private RedisService $redisClient;

    public function __construct(RedisService $redisClient)
    {
        $this->redisClient = $redisClient;
        $this->customNeemo = (new Custom())->getParams('neemo');
    }

    public function send(array $event): bool
    {
        if (!isset($event['orderId'])) {
            return false;
        }

        // Recupera o merchantId do pedido, se não encontrar o pedido no redis da como sucesso por ter passado o tempo limite
        $keyOrderDetails = str_replace('{order_id}', $event['orderId'], RedisSchema::KEY_ORDER_DETAILS);
        $orderDetails = $this->redisClient->get($keyOrderDetails);
        if (!$orderDetails) {
            return true;
        }

        $orderDetails = json_decode($orderDetails, true);
        $postFields = [
            'token_account' => $orderDetails['merchant_id'],
            'status' => OrderStatus::SHIPPED,
        ];
        $request = new Request("{$this->customNeemo['urlBase']}/order/{$event['orderId']}");
        $response = $request
            ->setRequestMethod('PUT')
            ->setPostFields($postFields)
            ->setRequestType(RequestConstants::CURLOPT_POST_NORMAL_DATA)
            ->setSaveLogs(true)
            ->execute();
        if ($response->http_code == RequestConstants::HTTP_OK) {
            return true;
        }
        return false;
    }
}