<?php

namespace src\ifood\Http;

use DateTime;
use DateTimeZone;
use src\Delivery\Enums\Provider;
use src\ifood\Enums\RedisSchema;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use src\ifood\Enums\OrderEntityType;

class OrderDetails
{
    private array $customIfood;
    private Oauth $oauth;
    private RedisService $redisClient;

    public function __construct(Oauth $oauth, RedisService $redisClient)
    {
        $this->oauth = $oauth;
        $this->redisClient = $redisClient;
        $this->customIfood = (new Custom())->getParams('ifood');
    }

    public function checkRateLimit(): bool
    {
        $currentTime = time();
        $windowTime = 65;
        $maxRequests = 3000;
        $keyRateLimit = RedisSchema::KEY_RATE_LIMIT_ORDERS;

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
        $request = new Request("{$this->customIfood['uri']}/logistics/v1.0/orders/{$orderId}");
        $response = $request
            ->setRequestMethod('GET')
            ->setHeaders($this->oauth->getHeadersRequests())
            ->setSaveLogs(true)
            ->execute();
        if ($response->http_code != RequestConstants::HTTP_OK) {
            return [];
        }

        $orderDetails = json_decode($response->content, true) ?: [];
        if (!isset($orderDetails['id'])) {
            return [];
        }

        return $orderDetails;
    }

    public function formatOrder(array $orderDetails): array
    {
        $scheduleDateInApproved = null;
        if (isset($orderDetails['schedule']['deliveryDateTimeStart'], $orderDetails['schedule']['deliveryDateTimeEnd'])) {
            $deliveryDateTimeStart = new DateTime($orderDetails['schedule']['deliveryDateTimeStart']);
            $deliveryDateTimeEnd = new DateTime($orderDetails['schedule']['deliveryDateTimeEnd']);
            $scheduleDateInApproved = new DateTime('now', new DateTimeZone('UTC'));

            if ($scheduleDateInApproved < $deliveryDateTimeStart) {
                $scheduleDateInApproved = $deliveryDateTimeStart;
            } elseif ($scheduleDateInApproved > $deliveryDateTimeEnd) {
                $scheduleDateInApproved = $deliveryDateTimeEnd;
            }

            $scheduleDateInApproved = $scheduleDateInApproved->format('Y-m-d\TH:i:s\Z');
        }

        $orderDetailsFormatted = [
            'orderId' => $orderDetails['id'],
            'displayId' => $orderDetails['displayId'],
            'merchantId' => $orderDetails['merchant']['id'],
            'provider' => Provider::IFOOD,
            'details' => [
                'orderType' => $orderDetails['orderType'],
                'deliveredBy' => $orderDetails['delivery']['deliveredBy'],
                'createdAt' => $orderDetails['createdAt'],
                'scheduleDateInApproved' => $scheduleDateInApproved,
                'deliveryAddress' => [
                    'streetName' => $orderDetails['delivery']['deliveryAddress']['streetName'],
                    'streetNumber' => $orderDetails['delivery']['deliveryAddress']['streetNumber'],
                    'formattedAddress' => $orderDetails['delivery']['deliveryAddress']['formattedAddress'],
                    'neighborhood' => $orderDetails['delivery']['deliveryAddress']['neighborhood'],
                    'complement' => $orderDetails['delivery']['deliveryAddress']['complement'] ?? null,
                    'postalCode' => $orderDetails['delivery']['deliveryAddress']['postalCode'],
                    'city' => $orderDetails['delivery']['deliveryAddress']['city'],
                    'state' => $orderDetails['delivery']['deliveryAddress']['state'],
                    'country' => $orderDetails['delivery']['deliveryAddress']['country'],
                    'reference' => $orderDetails['delivery']['deliveryAddress']['reference'] ?? null,
                    'coordinates' => [
                        'latitude' => $orderDetails['delivery']['deliveryAddress']['coordinates']['latitude'],
                        'longitude' => $orderDetails['delivery']['deliveryAddress']['coordinates']['longitude'],
                    ],
                ],
                'customer' => [
                    'name' =>  $orderDetails['customer']['name'] ?? null,
                    'phone' => $orderDetails['customer']['phone']['number'] ?? null,
                    'phoneLocalizer' => $orderDetails['customer']['phone']['localizer'] ?? null,
                ],
                'payments' => [
                    'pending' => $orderDetails['payments']['pending'],
                ],
            ],
        ];

        if ($orderDetails['isTest']) {
            $orderDetailsFormatted['details']['deliveryAddress'] = [
                'streetName' => 'Ramal Bujari',
                'streetNumber' => '100',
                'formattedAddress' => 'Ramal Bujari, 100',
                'neighborhood' => 'Bujari',
                'complement' => 'Complemento TESTE',
                'postalCode' => '69926000',
                'city' => 'Bujari',
                'state' => 'AC',
                'country' => 'BR',
                'reference' => 'Referência TESTE',
                'coordinates' => [
                    'latitude' => -9.822159000,
                    'longitude' => -67.948475000,
                ],
            ];
        }

        return $orderDetailsFormatted;
    }

    public function filterOrderAvailable(array $orderDetails): bool
    {
        // Pedidos que não são para entregar devem ser ignorados
        if ($orderDetails['details']['orderType'] != OrderEntityType::DELIVERY) {
            return false;
        }

        // Pedidos que não são responsabilidade do merchant a entrega, devem ser ignorados
        if ($orderDetails['details']['deliveredBy'] != OrderEntityType::MERCHANT) {
            return false;
        }

        return true;
    }
}