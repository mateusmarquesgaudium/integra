<?php

namespace src\Aiqfome\Handlers;

use src\Delivery\Enums\OrderStatus;
use src\Delivery\Enums\OrderType;
use src\Aiqfome\Enums\RedisSchema;
use src\Delivery\Enums\RedisSchema as DeliveryRedisSchema;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RequestMulti;
use src\geral\Util;

class CheckOrderDetailsHandler
{
    private RequestMulti $requestMulti;
    private Custom $custom;

    public function __construct(RequestMulti $requestMulti, Custom $custom)
    {
        $this->requestMulti = $requestMulti;
        $this->custom = $custom;
    }

    public function execute(array $ordersEvents, array &$pipeline): void
    {
        $responses = $this->requestMulti->execute();
        $customAiqfome = $this->custom->getParams('aiqfome');

        foreach ($ordersEvents as $orderResult) {
            $response = $responses[$orderResult['order_id']] ?? null;
            if (empty($response)) {
                continue;
            }

            if ($response->http_code === RequestConstants::HTTP_UNAUTHORIZED) {
                $pipeline[] = ['sAdd', RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH, $orderResult['merchant_id']];
                continue;
            }

            if ($response->http_code === RequestConstants::HTTP_NOT_FOUND) {
                continue;
            }

            if ($response->http_code !== RequestConstants::HTTP_OK) {
                $orderResult['nextMultiplierToRetry'] = isset($orderResult['nextMultiplierToRetry']) ? $orderResult['nextMultiplierToRetry'] * 2 : 1;
                $orderResult['nextTimeToRetry'] = Util::getNextTimeToRetry($orderResult['nextMultiplierToRetry'], $customAiqfome['timeToRetry']);
                $orderResult['attempts'] = ($orderResult['attempts'] ?? 0) + 1;
                $orderResult['errors'][] = [
                    'http_code' => $response->http_code,
                    'message' => $response->content,
                ];
                $pipeline[] = ['rPush', RedisSchema::KEY_LIST_ORDERS_EVENTS, json_encode($orderResult)];
                continue;
            }

            if ($response->http_code === RequestConstants::HTTP_OK) {
                $orderDetails = json_decode($response->content, true);
                $orderDetails = $orderDetails['data'] ?? [];
                $orderDetails = $this->formatOrderResult($orderDetails);

                if (empty($orderDetails)) {
                    continue;
                }

                $pipeline[] = ['rPush', DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, json_encode($orderDetails)];
            }
        }
    }

    private function formatOrderResult(array $orderDetails): array
    {
        if (empty($orderDetails)) {
            return [];
        }

        if ((isset($orderDetails['is_aiqentrega_delivery']) && $orderDetails['is_aiqentrega_delivery']) || (isset($orderDetails['is_pickupt']) && $orderDetails['is_pickup'])) {
            return [];
        }

        $dateUtc = new \DateTime('now', new \DateTimeZone('UTC'));
        return [
            'merchant_id' => $orderDetails['store']['id'],
            'event_created_at' => $dateUtc->format('Y-m-d\TH:i:s\Z'),
            'provider' => 'aiqfome',
            'order_id' => $orderDetails['id'],
            'order_status' => OrderStatus::APPROVED,
            'event_details' => [
                'merchant' => $orderDetails['store']['name'],
                'orderId' => $orderDetails['id'],
                'displayId' => $orderDetails['id'],
                'merchantId' => $orderDetails['store']['id'],
                'provider' => 'aiqfome',
                'details' => [
                    'orderType' => OrderType::DELIVERY,
                    'createdAt' => $dateUtc->format('Y-m-d\TH:i:s\Z'),
                    'deliveryAddress' => [
                        'coordinates' => [
                            'latitude' => $orderDetails['user']['address']['latitude'] ?? '',
                            'longitude' => $orderDetails['user']['address']['longitude'] ?? '',
                        ],
                        'formattedAddress' => $orderDetails['user']['address']['street_name'] . ', ' . $orderDetails['user']['address']['number'],
                        'complement' => $orderDetails['user']['address']['complement'],
                        'neighborhood' => $orderDetails['user']['address']['neighborhood_name'],
                        'city' => $orderDetails['user']['address']['city_name'],
                        'state' => $orderDetails['user']['address']['state_uf'],
                        'postalCode' => $orderDetails['user']['address']['zip_code'],
                        'reference' => $orderDetails['user']['address']['reference'] ?? null,
                    ],
                    'customer' => [
                        'name' => $orderDetails['user']['name'],
                        'phone' => $orderDetails['user']['mobile_phone'] ?: ($orderDetails['user']['phone_number'] ?: null),
                    ],
                    'payments' => [
                        'pending' => $orderDetails['payment_method']['pre_paid'] ? null : floatval($orderDetails['payment_method']['total']),
                    ]
                ],
            ]
        ];
    }
}