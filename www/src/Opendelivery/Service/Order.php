<?php

namespace src\Opendelivery\Service;

use DateTime;
use DateTimeZone;
use src\Delivery\Enums\OrderStatus;
use src\Delivery\Enums\OrderType;
use src\Delivery\Enums\Provider;
use src\geral\RedisService;
use src\Opendelivery\Enums\RedisSchema;
use src\Delivery\Enums\RedisSchema as DeliveryRedisSchema;

class Order
{
    private RedisService $redisService;
    private string $provider;

    public function __construct(RedisService $redisService, string $provider)
    {
        $this->redisService = $redisService;
        $this->provider = $provider;
    }

    public function setOrderDetails(array $orderDetails)
    {
        $key = str_replace(['{provider}', '{order_id}'], [$this->provider, $orderDetails['id']], RedisSchema::KEY_ORDER_DETAILS);
        $this->redisService->set($key, json_encode($orderDetails));
        $this->redisService->expire($key, RedisSchema::TTL_ORDER_DETAILS);
    }

    public function setOrderToSendWebhook(array $order)
    {
        $formattedOrder = $this->formatOrder($order, $this->provider);
        if (empty($formattedOrder)) {
            return;
        }

        $this->redisService->rPush(DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, json_encode($formattedOrder));
        $this->setOrderDetails($order);
    }

    private function formatOrder($orderDetail, $provider) : array
    {
        $order = [];
        if (empty($orderDetail)) {
            return $order;
        }

        if ($orderDetail['type'] !== 'DELIVERY' || $orderDetail['delivery']['deliveredBy'] === 'MARKETPLACE') {
            return $order;
        }

        $dateUtc = new DateTime('now', new DateTimeZone('UTC'));
        $orderCreatedAt = $dateUtc->format('Y-m-d\TH:i:s\Z');
        if ($orderDetail['orderTiming'] === 'SCHEDULED' && $provider !== Provider::CARDAPIOWEB) {
            $orderCreatedAt = $orderDetail['preparationStartDateTime'];
        }

        $order = [
            'order_status' => OrderStatus::APPROVED,
            'provider' => $provider,
            'event_created_at' => $dateUtc->format('Y-m-d\TH:i:s\Z'),
            'order_id' => $orderDetail['id'],
            'merchant_id' => $orderDetail['merchant']['id'],
            'event_details' => [
                'merchant' => $orderDetail['merchant']['name'],
                'orderId' => $orderDetail['id'],
                'displayId' => $orderDetail['displayId'],
                'merchantId' => $orderDetail['merchant']['id'],
                'provider' => $provider,
                'details' => [
                    'orderType' => OrderType::DELIVERY,
                    'createdAt' => $orderCreatedAt,
                    'deliveryAddress' => [
                        'coordinates' => [
                            'latitude' => $orderDetail['delivery']['deliveryAddress']['coordinates']['latitude'],
                            'longitude' => $orderDetail['delivery']['deliveryAddress']['coordinates']['longitude'],
                        ],
                        'formattedAddress' => $orderDetail['delivery']['deliveryAddress']['street'] . ', ' . $orderDetail['delivery']['deliveryAddress']['number'],
                        'complement' => $orderDetail['delivery']['deliveryAddress']['complement'],
                        'neighborhood' => $orderDetail['delivery']['deliveryAddress']['district'],
                        'city' => $orderDetail['delivery']['deliveryAddress']['city'],
                        'state' => $orderDetail['delivery']['deliveryAddress']['state'],
                        'postalCode' => $orderDetail['delivery']['deliveryAddress']['postal_code'],
                        'reference' => $orderDetail['delivery']['deliveryAddress']['reference'] ?? null,
                    ],
                    'customer' => [
                        'name' => $orderDetail['customer']['name'],
                        'phone' => $orderDetail['customer']['phone']['number'] ?? null,
                    ],
                    'payments' => [
                        'pending' => $orderDetail['payments']['pending'] ?? null,
                    ]
                ]
            ]
        ];

        return $order;
    }
}
