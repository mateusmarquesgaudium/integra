<?php

namespace src\Neemo\Http;

use DateInterval;
use DateTime;
use DateTimeZone;
use src\Neemo\Enums\OrderStatus;
use src\Neemo\Enums\RedisSchema;
use src\Delivery\Enums\OrderStatus as DeliveryOrderStatus;
use src\Delivery\Enums\OrderType as DeliveryOrderType;
use src\Delivery\Enums\Provider;
use src\Delivery\Enums\RedisSchema as DeliveryRedisSchema;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use src\geral\Util;
use src\Neemo\Enums\OrderType;

class OrderDetails
{
    // Tempo de antecedência em minutos dos pedidos programados
    private int $scheduledLeadTimeInMinutes = 20;
    private string $merchantId;
    private array $customNeemo;
    private RedisService $redisClient;

    public function __construct(RedisService $redisClient, string $merchantId)
    {
        $this->merchantId = $merchantId;
        $this->redisClient = $redisClient;
        $this->customNeemo = (new Custom())->getParams('neemo');
    }

    public function checkRateLimit(): bool
    {
        $currentTime = time();
        $windowTime = 60;
        $maxRequests = 1000;
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

    public function orderStillPending(string $orderId, string $merchantId, array &$pipeline): bool
    {
        $orderDetails = $this->requestOrderDetails($orderId);
        if (!isset($orderDetails['status'])) {
            return true;
        }

        // Não tem forma de entrega ou não é Delivery
        if (empty($orderDetails['forma_entrega']) || $orderDetails['forma_entrega'] != OrderType::DELIVERY) {
            return false;
        }

        // Ainda vai ser confirmado ou aguardando pagamento online
        if (in_array($orderDetails['status'], [OrderStatus::NEW_ORDER, OrderStatus::AWAITING_ONLINE_PAYMENT_APPROVAL])) {
            return true;
        }

        // Outros status diferentes de confirmado
        if (!in_array($orderDetails['status'], [OrderStatus::CONFIRMED])) {
            return false;
        }

        $orderDetailsFormatted = [
            'merchant_id' => $merchantId,
            'event_created_at' => (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            'provider' => Provider::NEEMO,
            'order_id' => $orderId,
            'order_status' => DeliveryOrderStatus::APPROVED,
            'event_details' => $this->formatOrder($orderDetails),
        ];

        // Pedido confirmado, envia para a fila de eventos via webhook
        $pipeline[] = ['rPush', DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, json_encode($orderDetailsFormatted)];

        if (!empty($orderDetails['is_scheduled']) && $this->customNeemo['orderModeActived']) {
            $dateTime = Util::convertDateWithTimeZoneToDateTimeUtc($orderDetails['scheduled_at']);
            $dateTime->modify('+4 hours');
            // 4 horas em relação a quando foi agendada
            $ttlOrderDetails = Util::calculateTtlFromUtcDateTime($dateTime);
        } else {
            $ttlOrderDetails = 60 * 60 * 4; // 4 horas
        }

        $pipeline[] = ['set', str_replace('{order_id}', $orderId, RedisSchema::KEY_ORDER_DETAILS), json_encode($orderDetailsFormatted), ['EX' => $ttlOrderDetails]];

        $pipeline[] = ['rPush', RedisSchema::KEY_LIST_MONITORING_ORDER_STATUS, json_encode([
            'order_id' => $orderId,
            'merchant_id' => $merchantId,
        ])];
        return false;
    }

    public function checkNeedMonitorTheOrder(string $orderId, array &$pipeline): bool
    {
        $orderDetails = $this->requestOrderDetails($orderId);
        if (!isset($orderDetails['status'])) {
            return true;
        }

        if ($orderDetails['status'] == OrderStatus::CONFIRMED) {
            return true;
        }

        // Gera evento de IN_TRANSIT
        if ($orderDetails['status'] == OrderStatus::SHIPPED && $this->customNeemo['orderModeActived']) {
            $eventWebhook = [
                'provider' => Provider::NEEMO,
                'order_id' => $orderId,
                'order_status' => DeliveryOrderStatus::IN_TRANSIT,
                'event_created_at' => (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            ];
            $pipeline[] = ['rPush', DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, json_encode($eventWebhook)];
            return false;
        }

        // Qualquer outro status que não precisa de tratamento para gerar eventos e continuar monitorando
        return false;
    }

    private function requestOrderDetails(string $orderId): ?array
    {
        $request = new Request("{$this->customNeemo['urlBase']}/order/{$orderId}");
        $response = $request
            ->setRequestMethod('POST')
            ->setPostFields(['token_account' => $this->merchantId])
            ->setRequestType(RequestConstants::CURLOPT_POST_NORMAL_DATA)
            ->setSaveLogs(true)
            ->execute();
        if (!in_array($response->http_code, [RequestConstants::HTTP_OK, RequestConstants::HTTP_ACCEPTED, RequestConstants::HTTP_CREATED])) {
            // Retorna true para análise de tratativas futuras
            return null;
        }

        $orderDetails = json_decode($response->content, true) ?: [];
        if (!isset($orderDetails['code'], $orderDetails['Order']) || $orderDetails['code'] !== RequestConstants::HTTP_OK) {
            // Retorna true para análise de tratativas futuras
            return null;
        }

        return $orderDetails['Order'];
    }

    private function formatOrder(array $orderDetails): array
    {
        $isScheduled = (!empty($orderDetails['is_scheduled']) && $this->customNeemo['orderModeActived']);

        $orderDetailsFormatted = [
            'orderId' => $orderDetails['id'],
            'displayId' => $orderDetails['order_number'],
            'merchantId' => $this->merchantId,
            'provider' => Provider::NEEMO,
            'details' => [
                'orderType' => DeliveryOrderType::DELIVERY,
                'createdAt' => Util::convertDateWithTimeZoneToDateTimeUtc($orderDetails['date'])->format('Y-m-d\TH:i:s\Z'),
                'scheduleDateInApproved' => $isScheduled ? Util::convertDateWithTimeZoneToDateTimeUtc($orderDetails['scheduled_at'])->format('Y-m-d\TH:i:s\Z') : null,
                'paymentMethod' => $orderDetails['payment_method'],
                'delivered' => !$this->customNeemo['orderModeActived'],
                'deliveryAddress' => [
                    'coordinates' => [
                        'latitude' => $orderDetails['latitude'],
                        'longitude' => $orderDetails['longitude'],
                    ],
                    'formattedAddress' => null,
                    'complement' => $orderDetails['complement'] ?: null,
                    'neighborhood' => $orderDetails['neighborhood'],
                    'city' => $orderDetails['city'],
                    'state' => $orderDetails['uf'],
                    'reference' => $orderDetails['reference_point'] ?: null,
                ],
                'customer' => [
                    'name' =>  $orderDetails['name'],
                    'phone' => $orderDetails['phone'] ?? null,
                ],
                'payments' => [
                    'pending' => $orderDetails['payment_online'] ? null : floatval($orderDetails['total']),
                ],
            ],
        ];

        $partsFormattedAddress = [];
        if (!empty($orderDetails['street'])) {
            $partsFormattedAddress[] = $orderDetails['street'];
        }
        if (!empty($orderDetails['number'])) {
            $partsFormattedAddress[] = $orderDetails['number'];
        }
        $orderDetailsFormatted['details']['deliveryAddress']['formattedAddress'] = implode(', ', $partsFormattedAddress);

        if (!empty($orderDetails['scheduleDateInApproved'])) {
            $scheduleDate = Util::convertDateWithTimeZoneToDateTimeUtc($orderDetails['scheduleDateInApproved']);
            $scheduleDate->sub(new DateInterval("PT{$this->scheduledLeadTimeInMinutes}M"));
            $orderDetailsFormatted['details']['scheduleDateInApproved'] = $scheduleDate->format('Y-m-d\TH:i:s\Z');
        }
        return $orderDetailsFormatted;
    }
}
