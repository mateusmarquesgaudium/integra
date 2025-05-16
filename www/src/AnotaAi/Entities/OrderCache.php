<?php

namespace src\AnotaAi\Entities;

use DateTime;
use DateTimeZone;
use src\Delivery\Enums\Provider;
use src\Delivery\Enums\RedisSchema as DeliveryRedisSchema;
use src\AnotaAi\Enums\RedisSchema;
use src\Delivery\Enums\EventWebhookType;
use src\Delivery\Enums\OrderStatus;
use src\Delivery\Enums\OrderType;
use src\geral\RedisService;

class OrderCache
{
    private RedisService $redisClient;

    public function __construct(RedisService $redisClient)
    {
        $this->redisClient = $redisClient;
    }

    public function addEventOrderWebhook(string $merchantId, string $orderId, array $orderDetails): void
    {
        $dateUtc = new DateTime();
        $dateUtc->setTimezone(new DateTimeZone('UTC'));

        $orderCache = [
            'merchant_id' => $merchantId,
            'event_created_at' => $dateUtc->format('Y-m-d\TH:i:s\Z'),
            'provider' => Provider::ANOTAAI,
            'order_id' => $orderId,
        ];

        if (!empty($orderDetails['salesChannel']) && $orderDetails['salesChannel'] == Provider::IFOOD) {
            return;
        }

        if ($orderDetails['webhookType'] == EventWebhookType::ANOTAAI_CREATED && $orderDetails['type'] == OrderType::DELIVERY) {
            $orderCache['order_status'] = OrderStatus::APPROVED;
            $orderCache['event_details'] = $this->formatOrder($orderDetails);
            $this->redisClient->rPush(RedisSchema::KEY_LIST_PENDING_ORDER_EVENTS, json_encode($orderCache));
            return;
        }

        if ($orderDetails['webhookType'] == EventWebhookType::ANOTAAI_CANCELED) {
            $orderCache['order_status'] = OrderStatus::HIDDEN;
            $this->redisClient->rPush(DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, json_encode($orderCache));
            $this->redisClient->del(str_replace('{order_id}', $orderId, RedisSchema::KEY_ORDER_DETAILS));
            return;
        }
    }

    /**
     * Calculates the total pending value based on the payment information.
     *
     * @param array $paymentInfo An array containing payment information.
     * @return null|float The total pending value or null if there is no pending value.
     */
    private function getTotalPendingValueFromOrder(array $paymentInfo) : ?float
    {
        $totalPending = 0;
        foreach ($paymentInfo as $payment) {
            if (!$payment['prepaid']) {
                $totalPending += floatval($payment['value']);
            }
        }
        return $totalPending ?: null;
    }

    private function formatOrder(array $orderDetails): array
    {
        return [
            'orderId' => $orderDetails['id'],
            'displayId' => $orderDetails['shortReference'] ?? $orderDetails['id'],
            'merchantId' => $orderDetails['merchant']['id'],
            'provider' => Provider::ANOTAAI,
            'details' => [
                'orderType' => $orderDetails['type'],
                'createdAt' => $orderDetails['createdAt'],
                'scheduleDateInApproved' => $orderDetails['schedule_order']['date'] ?? null,
                'deliveryAddress' => [
                    'coordinates' => [
                        'latitude' => $orderDetails['deliveryAddress']['coordinates']['latitude'] ?? null,
                        'longitude' => $orderDetails['deliveryAddress']['coordinates']['longitude'] ?? null,
                    ],
                    'formattedAddress' => $orderDetails['deliveryAddress']['formattedAddress'],
                    'complement' => $orderDetails['deliveryAddress']['complement'] ?: null,
                    'neighborhood' => $orderDetails['deliveryAddress']['neighborhood'] ?? null,
                    'city' => $orderDetails['deliveryAddress']['city'],
                    'state' => $orderDetails['deliveryAddress']['state'],
                    'reference' => $orderDetails['deliveryAddress']['reference'] ?: null,
                ],
                'customer' => [
                    'name' =>  $orderDetails['customer']['name'] ?? null,
                    'phone' => $orderDetails['customer']['phone'] ?? null,
                ],
                'payments' => [
                    'pending' => $this->getTotalPendingValueFromOrder($orderDetails['payments']),
                ],
            ],
        ];
    }
}