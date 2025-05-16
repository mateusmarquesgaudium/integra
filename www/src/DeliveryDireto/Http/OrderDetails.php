<?php

namespace src\DeliveryDireto\Http;

use DateTime;
use DateTimeZone;
use src\Delivery\Enums\Provider;
use src\DeliveryDireto\Enums\RedisSchema;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;

class OrderDetails
{
    private array $customDeliveryDireto;
    private Oauth $oauth;
    private RedisService $redisClient;

    public function __construct(Oauth $oauth, RedisService $redisClient)
    {
        $this->oauth = $oauth;
        $this->redisClient = $redisClient;
        $this->customDeliveryDireto = (new Custom())->getParams('delivery_direto');
    }

    public function checkRateLimit(string $merchantId): bool
    {
        $currentTime = time();
        $windowTime = 65;
        $maxRequests = 3;
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

    public function searchDetails(string $orderId): array
    {
        $request = new Request("{$this->customDeliveryDireto['urlStoreAdmin']}/orders?ordersId={$orderId}");
        $response = $request
            ->setRequestMethod('GET')
            ->setHeaders($this->oauth->getHeadersRequests())
            ->setSaveLogs(true)
            ->execute();
        if ($response->http_code != RequestConstants::HTTP_OK) {
            return [];
        }

        $orderDetails = json_decode($response->content, true);
        if (!isset($orderDetails['data'], $orderDetails['data']['orders'], $orderDetails['data']['orders'][0])) {
            return [];
        }

        $orderDetails = $orderDetails['data']['orders'][0];
        return $orderDetails;
    }

    public function formatOrder(array $orderDetails): array
    {
        $orderDetailsFormatted = [
            'orderId' => $orderDetails['id'],
            'displayId' => $orderDetails['orderNumber'],
            'merchantId' => $orderDetails['merchant_id'],
            'provider' => Provider::DELIVERY_DIRETO,
            'details' => [
                'orderType' => $orderDetails['type'],
                'createdAt' => $orderDetails['created'],
                'scheduleDateInApproved' => $orderDetails['scheduledOrder']['appearDate'] ?? null,
                'deliveryAddress' => [
                    'coordinates' => [
                        'latitude' => $orderDetails['address']['lat'],
                        'longitude' => $orderDetails['address']['lng'],
                    ],
                    'formattedAddress' => null,
                    'complement' => $orderDetails['address']['complement'] ?: null,
                    'neighborhood' => $orderDetails['address']['neighborhood'],
                    'city' => $orderDetails['address']['city'],
                    'state' => $orderDetails['address']['state'],
                    'reference' => $orderDetails['address']['reference_point'] ?: null,
                ],
                'customer' => [
                    'name' =>  null,
                    'phone' => $orderDetails['customer']['telephone'] ?? null,
                ],
                'payments' => [
                    'pending' => $orderDetails['isOnlinePayment'] ? null : floatval($orderDetails['total']['total']['value']) / 100.0,
                ],
            ],
        ];

        $partsFormattedAddress = [];
        if (!empty($orderDetails['address']['street'])) {
            $partsFormattedAddress[] = $orderDetails['address']['street'];
        }
        if (!empty($orderDetails['address']['number'])) {
            $partsFormattedAddress[] = $orderDetails['address']['number'];
        }
        $orderDetailsFormatted['details']['deliveryAddress']['formattedAddress'] = implode(', ', $partsFormattedAddress);

        $partsCustomerName = [];
        if (!empty($orderDetails['customer']['firstName'])) {
            $partsCustomerName[] = $orderDetails['customer']['firstName'];
        }
        if (!empty($orderDetails['customer']['lastName'])) {
            $partsCustomerName[] = $orderDetails['customer']['lastName'];
        }
        $orderDetailsFormatted['details']['customer']['name'] = implode(' ', $partsCustomerName);

        if (!empty($orderDetailsFormatted['details']['scheduleDateInApproved'])) {
            $dateUtc = new DateTime($orderDetailsFormatted['details']['scheduleDateInApproved']);
            $dateUtc->setTimezone(new DateTimeZone('UTC'));
            $orderDetailsFormatted['details']['scheduleDateInApproved'] = $dateUtc->format('Y-m-d\TH:i:s\Z');
        }

        return $orderDetailsFormatted;
    }
}