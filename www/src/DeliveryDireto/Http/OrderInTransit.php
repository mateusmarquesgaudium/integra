<?php

namespace src\DeliveryDireto\Http;

use src\Delivery\Enums\OrderStatus;
use src\DeliveryDireto\Enums\RedisSchema;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;

class OrderInTransit
{
    private array $customDeliveryDireto;
    private RedisService $redisClient;

    public function __construct(RedisService $redisClient)
    {
        $this->redisClient = $redisClient;
        $this->customDeliveryDireto = (new Custom())->getParams('delivery_direto');
    }

    public function send(array $event): bool
    {
        if (!isset($event['orderId'])) {
            return false;
        }

        // Recupera o merchant_id do pedido, se não encontrar o pedido no redis da como sucesso por ter passado o tempo limite
        $keyOrderDetails = str_replace('{order_id}', $event['orderId'], RedisSchema::KEY_ORDER_DETAILS);
        $orderDetails = $this->redisClient->get($keyOrderDetails);
        if (!$orderDetails) {
            return true;
        }

        $orderDetails = json_decode($orderDetails, true);
        // Verifica se já atingiu o limite do endpoint de mudança de status
        if (!$this->checkRateLimit($orderDetails['merchant_id'])) {
            return false;
        }

        $keyCredential = str_replace('{merchant_id}', $orderDetails['merchant_id'], RedisSchema::KEY_CRENDENTIAL_MERCHANT);
        $credentials = json_decode($this->redisClient->get($keyCredential), true);
        if (!$credentials) {
            return false;
        }

        $oauth = new Oauth($this->redisClient, $orderDetails['merchant_id'], $credentials['username'], $credentials['password']);
        $request = new Request("{$this->customDeliveryDireto['urlStoreAdmin']}/orders/{$event['orderId']}/status");
        $response = $request
            ->setRequestMethod('PUT')
            ->setHeaders($oauth->getHeadersRequests())
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields([
                'status' => OrderStatus::IN_TRANSIT,
            ])
            ->setSaveLogs(true)
            ->execute();
        if (in_array($response->http_code, [RequestConstants::HTTP_NO_CONTENT, RequestConstants::HTTP_UNPROCESSABLE_ENTITY, RequestConstants::HTTP_NOT_FOUND])) {
            return true;
        }
        return false;
    }

    private function checkRateLimit(string $merchantId): bool
    {
        $currentTime = time();
        $windowTime = 65;
        $maxRequests = 30;
        $keyRateLimit = str_replace('{merchant_id}', $merchantId, RedisSchema::KEY_RATE_LIMIT_ORDER_STATUS);

        // Remover timestamps antigos
        $this->redisClient->zRemRangeByScore($keyRateLimit, 0, $currentTime - $windowTime);

        // Contar o número de requisições no intervalo atual
        $currentCount = $this->redisClient->zCard($keyRateLimit);

        if ($currentCount < $maxRequests) {
            // Ainda não atingiu o limite, então permite a requisição e registra o timestamp
            $this->redisClient->zAdd($keyRateLimit, $currentTime, $currentTime);
            return true;
        }
        return false;
    }
}