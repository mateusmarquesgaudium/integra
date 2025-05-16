<?php

namespace src\Anotaai\Http;

use src\Delivery\Enums\OrderStatus;
use src\AnotaAi\Enums\RedisSchema;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;

class OrderFinished
{
    private array $customAnotaAi;
    private RedisService $redisClient;

    public function __construct(RedisService $redisClient)
    {
        $this->redisClient = $redisClient;
        $this->customAnotaAi = (new Custom())->getParams('anotaai');
    }

    public function send(array $event, array &$pipeline): bool
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

        $headers = [
            'Content-Type: application/json',
            "Authorization: {$orderDetails['merchant_token']}",
        ];
        $request = new Request("{$this->customAnotaAi['urlBase']}/order/finalize/{$event['orderId']}");
        $response = $request
            ->setRequestMethod('POST')
            ->setHeaders($headers)
            ->setSaveLogs(true)
            ->execute();
        if ($response->http_code != RequestConstants::HTTP_OK) {
            return false;
        }

        $pipeline[] = ['del', str_replace('{order_id}', $event['orderId'], RedisSchema::KEY_ORDER_DETAILS)];
        return true;
    }

    private function checkRateLimit(string $merchantId): bool
    {
        $currentTime = time();
        $windowTime = 60;
        $maxRequests = 500;
        $keyRateLimit = str_replace('{merchant_id}', $merchantId, RedisSchema::KEY_RATE_LIMIT_ORDERS);

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