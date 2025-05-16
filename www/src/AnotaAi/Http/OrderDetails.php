<?php

namespace src\AnotaAi\Http;

use DateTime;
use DateTimeZone;
use src\AnotaAi\Enums\OrderStatus;
use src\AnotaAi\Enums\RedisSchema;
use src\Delivery\Enums\OrderStatus as DeliveryOrderStatus;
use src\Delivery\Enums\Provider;
use src\Delivery\Enums\RedisSchema as DeliveryRedisSchema;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use src\geral\Util;

class OrderDetails
{
    private string $authorizationToken;
    private string $merchantId;
    private array $customAnotaAi;
    private RedisService $redisClient;

    public function __construct(RedisService $redisClient, string $merchantId, string $authorizationToken)
    {
        $this->merchantId = $merchantId;
        $this->authorizationToken = $authorizationToken;
        $this->redisClient = $redisClient;
        $this->customAnotaAi = (new Custom())->getParams('anotaai');
    }

    public function checkRateLimit(): bool
    {
        $currentTime = time();
        $windowTime = 60;
        $maxRequests = 500;
        $keyRateLimit = str_replace('{merchant_id}', $this->merchantId, RedisSchema::KEY_RATE_LIMIT_ORDERS);

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

    public function orderStillPending(string $orderId, array $orderEvent, array &$pipeline): bool
    {
        $orderStatus = $this->requestOrderStatus($orderId);
        if (is_null($orderStatus)) {
            return true;
        }

        // Ainda em análise ou solicitação de cancelamento
        if (in_array($orderStatus, [OrderStatus::IN_REVIEW, OrderStatus::IN_CANCELLATION_REQUEST])) {
            return true;
        }

        // Outros status diferentes de em produção e agendamento aceito
        if (!in_array($orderStatus, [OrderStatus::IN_PRODUCTION, OrderStatus::APPOINTMENT_ACCEPTED])) {
            return false;
        }

        // Em produção ou agendamento aceito, envia para a fila de eventos via webhook
        $pipeline[] = ['rPush', DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, json_encode($orderEvent)];

        if (!empty($orderEvent['event_details']['details']['scheduleDateInApproved'])) {
            $dateTime = new DateTime($orderEvent['event_details']['details']['scheduleDateInApproved'], new DateTimeZone('UTC'));
            $dateTime->modify('+6 hours');
            // 6 horas em relação a quando foi agendada
            $ttlOrderDetails = Util::calculateTtlFromUtcDateTime($dateTime);
        } else {
            $ttlOrderDetails = 60 * 60 * 6; // 6 horas
        }

        $orderEvent['merchant_token'] = $this->authorizationToken;
        $pipeline[] = ['set', str_replace('{order_id}', $orderId, RedisSchema::KEY_ORDER_DETAILS), json_encode($orderEvent), ['EX' => $ttlOrderDetails]];

        $pipeline[] = ['rPush', RedisSchema::KEY_LIST_MONITORING_ORDER_STATUS, json_encode([
            'order_id' => $orderId,
            'merchant_id' => $orderEvent['merchant_id'],
            'merchant_token' => $this->authorizationToken,
        ])];
        return false;
    }

    public function checkNeedMonitorTheOrder(string $orderId, array $orderEvent, array &$pipeline): bool
    {
        $orderStatus = $this->requestOrderStatus($orderId);
        if (is_null($orderStatus)) {
            return true;
        }

        if (in_array($orderStatus, [OrderStatus::IN_PRODUCTION, OrderStatus::APPOINTMENT_ACCEPTED])) {
            return true;
        }

        // Gera evento de IN_TRANSIT
        if ($orderStatus == OrderStatus::READY) {
            $eventWebhook = [
                'provider' => Provider::ANOTAAI,
                'order_id' => $orderEvent['order_id'],
                'order_status' => DeliveryOrderStatus::IN_TRANSIT,
                'event_created_at' => (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            ];
            $pipeline[] = ['rPush', DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, json_encode($eventWebhook)];
            return false;
        }

        // Qualquer outro status que não precisa de tratamento para gerar eventos e continuar monitorando
        return false;
    }

    private function requestOrderStatus(string $orderId): ?int
    {
        $headers = [
            'Content-Type: application/json',
            "Authorization: {$this->authorizationToken}",
        ];
        $request = new Request("{$this->customAnotaAi['urlBase']}/ping/get/{$orderId}");
        $response = $request
            ->setRequestMethod('GET')
            ->setHeaders($headers)
            ->setSaveLogs(true)
            ->execute();
        if ($response->http_code != RequestConstants::HTTP_OK) {
            // Retorna true para análise de tratativas futuras
            return null;
        }

        $orderDetails = json_decode($response->content, true) ?: [];
        if (!isset($orderDetails['info'], $orderDetails['info']['check'])) {
            // Retorna true para análise de tratativas futuras
            return null;
        }

        return $orderDetails['info']['check'];
    }
}
