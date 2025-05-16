<?php

namespace src\Opendelivery\Handlers;

require_once __DIR__ . '/../../vendor/autoload.php';

use DateTime;
use DateTimeZone;
use Ramsey\Uuid\Uuid;
use src\Delivery\Enums\OrderStatus;
use src\Delivery\Enums\OrderType;
use src\geral\CustomException;
use src\geral\RedisService;
use src\Opendelivery\Entities\OrderCache;
use src\Opendelivery\Entities\OrderEvent;
use src\Opendelivery\Enums\DeliveryStatus;
use src\Opendelivery\Enums\RedisSchema;

class NewDeliveryHandler
{
    private RedisService $redisService;
    private string $provider;
    private OrderCache $orderCache;
    private array $response;

    public function __construct(RedisService $redisService, string $provider)
    {
        $this->redisService = $redisService;
        $this->provider = $provider;
        $this->orderCache = new OrderCache($this->redisService, $this->provider);
    }

    public function createNewDelivery($data): array
    {
        $this->checkPayments($data);

        $this->checkMerchant($data['merchant']['id']);

        $orderFormatted = $this->formatOrderToDelivery($data);
        $this->proccessOrder($orderFormatted);

        return $this->response;
    }

    public function getValidateFields(): array
    {
        return [
            'orderId',
            'orderDisplayId',
            'merchant',
            'merchant.id',
            'merchant.name',
            'pickupAddress',
            'pickupAddress.country',
            'pickupAddress.state',
            'pickupAddress.city',
            'pickupAddress.district',
            'pickupAddress.street',
            'pickupAddress.number',
            'pickupAddress.postalCode',
            'pickupAddress.complement',
            'returnToMerchant',
            'canCombine',
            'deliveryAddress',
            'deliveryAddress.country',
            'deliveryAddress.state',
            'deliveryAddress.city',
            'deliveryAddress.district',
            'deliveryAddress.street',
            'deliveryAddress.number',
            'deliveryAddress.postalCode',
            'deliveryAddress.complement',
            'customerName',
            'vehicle',
            'vehicle.type',
            'vehicle.container',
            'limitTimes',
            'totalOrderPrice',
            'totalOrderPrice.value',
            'totalOrderPrice.currency',
            'totalWeight',
        ];
    }

    private function checkMerchant(string $merchantId): void
    {
        $checkMerchant = $this->redisService->sisMember(str_replace('{provider}', $this->provider, RedisSchema::KEY_MERCHANTS_PROVIDER), $merchantId);
        if (empty($checkMerchant)) {
            throw new CustomException('Merchant is not registered.', 404);
        }
    }

    private function checkPayments($data): void
    {
        $missingParams = [];
        if (!isset($data['payments'], $data['payments']['method'])) {
            $missingParams[] = 'payments method';
        }

        if (empty($missingParams) && $data['payments']['method'] === 'ONLINE') {
            return;
        }

        if (!isset($data['payments']['offlineMethod']) || !is_array($data['payments']['offlineMethod'])) {
            $missingParams[] = 'payments offlineMethod';
        }

        if (count($missingParams) > 0) {
            throw new \Exception("Missing required parameters: " . implode(', ', $missingParams));
        }

        $hasTypeCash = false;
        $this->checkOfflinePayments($data['payments']['offlineMethod']);
    }

    private function checkOfflinePayments(array $offlineMethod): void
    {
        $missingParams = [];
        foreach ($offlineMethod as $key => $method) {
            if (!isset($method['amount'], $method['amount']['value'], $method['amount']['currency'])) {
                $missingParams[] = "payments offlineMethod $key amount";
            }
        }

        if (count($missingParams) > 0) {
            throw new \Exception("Missing required parameters: " . implode(', ', $missingParams));
        }
    }

    private function formatOrderToDelivery(array $orderDetails): array
    {
        $dateUtc = new DateTime();
        $dateUtc->setTimezone(new DateTimeZone('UTC'));
        $deliveryId = Uuid::uuid7();

        $phoneExplode = explode(',', $orderDetails['customerPhone'] ?? '');
        $customerPhone = $phoneExplode[0] ?? null;
        $customerPhoneLocalizer = !empty($phoneExplode[1]) ? $phoneExplode[1] : null;
        $customerPhoneLocalizer = $customerPhoneLocalizer ?? ($orderDetails['customerPhoneLocalizer'] ?? null);

        $offlinePayment = 0;
        if (isset($orderDetails['payments']['offlineMethod'])) {
            foreach ($orderDetails['payments']['offlineMethod'] as $payment) {
                $offlinePayment += floatval($payment['amount']['value']);
            }
        }

        $pendingPayment = empty($offlinePayment) ? floatval($orderDetails['totalOrderPrice']['value']) : $offlinePayment;

        $orderDetailsFormatted = [
            'merchant_id' => $orderDetails['merchant']['id'],
            'event_created_at' => $dateUtc->format('Y-m-d\TH:i:s\Z'),
            'provider' => $this->provider,
            'order_id' => $orderDetails['orderId'],
            'order_status' => OrderStatus::APPROVED,
            'delivery_id' => $deliveryId,
            'event_details' => [
                'merchant' => $orderDetails['merchant']['name'],
                'orderId' => $orderDetails['orderId'],
                'deliveryId' => $deliveryId,
                'displayId' => $orderDetails['orderDisplayId'],
                'merchantId' => $orderDetails['merchant']['id'],
                'provider' => $this->provider,
                'details' => [
                    'orderType' => OrderType::DELIVERY,
                    'createdAt' => $dateUtc->format('Y-m-d\TH:i:s\Z'),
                    'deliveryAddress' => [
                        'coordinates' => [
                            'latitude' => $orderDetails['deliveryAddress']['latitude'] ?? '',
                            'longitude' => $orderDetails['deliveryAddress']['longitude'] ?? '',
                        ],
                        'formattedAddress' => $orderDetails['deliveryAddress']['street'] . ', ' . $orderDetails['deliveryAddress']['number'],
                        'complement' => $orderDetails['deliveryAddress']['complement'],
                        'neighborhood' => $orderDetails['deliveryAddress']['district'],
                        'city' => $orderDetails['deliveryAddress']['city'],
                        'state' => $orderDetails['deliveryAddress']['state'],
                        'postalCode' => $orderDetails['deliveryAddress']['postalCode'],
                        'reference' => $orderDetails['deliveryAddress']['reference'] ?? null,
                    ],
                    'customer' => [
                        'name' => $orderDetails['customerName'],
                        'phone' => $customerPhone,
                        'phoneLocalizer' => $customerPhoneLocalizer,
                    ],
                    'payments' => [
                        'pending' => $orderDetails['payments']['method'] === 'ONLINE' ? null : $pendingPayment,
                    ]
                ],
                'return' => $orderDetails['returnToMerchant'],
            ]
        ];

        return $orderDetailsFormatted;
    }

    private function proccessOrder(array $orderFormatted): void
    {
        $orderFormatted['order_status'] = OrderStatus::APPROVED;
        $this->orderCache->addEventOrderWebhook($orderFormatted);

        $rejectAfter = $this->calculateRejectAfterDateTime();
        $deliveryDetailsURL = $this->generateDeliveryDetailsURL($orderFormatted['order_id']);

        $this->response = $this->prepareOrderData($orderFormatted, $rejectAfter, $deliveryDetailsURL);
        $this->saveCreatedOrderCache($orderFormatted);
    }

    private function calculateRejectAfterDateTime(): DateTime
    {
        $utcTimeZone = new DateTimeZone('UTC');
        $rejectAfter = new DateTime('now', $utcTimeZone);
        $rejectAfter->modify('+1 day +2 hours');
        return $rejectAfter;
    }

    private function generateDeliveryDetailsURL(string $orderId): string
    {
        $protocol = $this->getRequestProtocol();
        return "{$protocol}{$_SERVER['HTTP_HOST']}/integra/opendelivery/v1/logistics/delivery/{$orderId}";
    }

    private function getRequestProtocol(): string
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    }

    private function prepareOrderData(array $orderFormatted, DateTime $rejectAfter, string $deliveryDetailsURL): array
    {
        $order = [
            'deliveryId' => $orderFormatted['delivery_id'],
            'event' => DeliveryStatus::PENDING,
            'completion' => ['rejectAfter' => $rejectAfter->format('c')],
            'deliveryDetailsURL' => $deliveryDetailsURL,
        ];
        return $order;
    }

    private function saveCreatedOrderCache(array $orderFormatted): void
    {
        $orderEvent = new OrderEvent(DeliveryStatus::PENDING);
        $order = [
            'lastEvent' => $orderEvent->type,
            'deliveryId' => $orderFormatted['delivery_id'],
            'orderId' => $orderFormatted['order_id'],
            'orderDisplayId' => $orderFormatted['event_details']['displayId'],
            'merchant' => [
                'id' => $orderFormatted['merchant_id'],
                'name' => $orderFormatted['event_details']['merchant']
            ],
            'customerName' => $orderFormatted['event_details']['details']['customer']['name'],
            'customerPhone' => $orderFormatted['event_details']['details']['customer']['phone'],
            'events' => [$orderEvent->toEventFormat()]
        ];

        $this->orderCache->addOrderCache($order['orderId'], $order);
    }
}