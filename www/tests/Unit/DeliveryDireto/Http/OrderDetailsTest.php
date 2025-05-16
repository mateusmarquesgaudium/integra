<?php

use src\DeliveryDireto\Http\Oauth;
use src\DeliveryDireto\Http\OrderDetails;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use Tests\Helpers\MockHelpers\RequestMockHelper;

use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);
    $this->oauth = $this->createMock(Oauth::class);
    $this->oauth->method('getHeadersRequests')->willReturn([]);

    $this->merchantId = fake()->uuid();
    $this->orderId = fake()->uuid();
    $this->orderDetails = new OrderDetails($this->oauth, $this->redisClient);
});

describe('checkRateLimit', function () {
    test('taxa dentro do limite (ratelimit)', function () {
        $this->redisClient
            ->method('zRemRangeByScore')
            ->willReturn(1);
        $this->redisClient
            ->method('zCard')
            ->willReturn(1);
        $this->redisClient
            ->method('zAdd')
            ->willReturn(1);

        $result = $this->orderDetails->checkRateLimit($this->merchantId);
        expect($result)->toBeTrue();
    });

    test('taxa excede o limite (ratelimit)', function () {
        $this->redisClient
            ->method('zRemRangeByScore')
            ->willReturn(1);
        $this->redisClient
            ->method('zCard')
            ->willReturn(4);

        $result = $this->orderDetails->checkRateLimit($this->merchantId);
        expect($result)->toBeFalse();
    });
});

describe('searchDetails', function () {
    test('requisição inválida', function () {
        RequestMockHelper::createExternalMock(RequestConstants::HTTP_FORBIDDEN, []);

        $result = $this->orderDetails->searchDetails($this->orderId);
        expect($result)->toBeArray()->toBeEmpty();
    });

    test('resposta sem pedidos', function () {
        RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
            'data' => [
                'orders' => []
            ]
        ]);

        $result = $this->orderDetails->searchDetails($this->orderId);
        expect($result)->toBeArray()->toBeEmpty();
    });

    test('resposta com pedidos', function () {
        $orderDetails = [
            'id' => fake()->uuid()
        ];
        RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
            'data' => [
                'orders' => [$orderDetails]
            ]
        ]);

        $result = $this->orderDetails->searchDetails($this->orderId);
        expect($result)->toBeArray()->toBe($orderDetails);
    });
});

describe('formatOrder', function () {
    test('formata o pedido', function () {
        $orderDetails = [
            'id' => fake()->uuid(),
            'orderNumber' => fake()->uuid(),
            'merchant_id' => fake()->uuid(),
            'type' => fake()->word(),
            'created' => fake()->date(),
            'isOnlinePayment' => fake()->boolean(),
            'scheduledOrder' => [
                'appearDate' => fake()->date(),
            ],
            'address' => [
                'street' => fake()->streetName(),
                'number' => fake()->buildingNumber(),
                'complement' => fake()->word(),
                'neighborhood' => fake()->word(),
                'city' => fake()->city(),
                'state' => fake()->state(),
                'lat' => fake()->latitude(),
                'lng' => fake()->longitude(),
                'reference_point' => fake()->word(),
            ],
            'customer' => [
                'firstName' => fake()->firstName(),
                'lastName' => fake()->phoneNumber(),
            ],
            'total' => [
                'total' => [
                    'value' => fake()->randomFloat(2, 0, 1000),
                ],
            ],
        ];

        $orderDetailsFormatted = $this->orderDetails->formatOrder($orderDetails);
        expect($orderDetailsFormatted)->toBeArray()->toHaveKeys(['orderId', 'displayId', 'merchantId', 'provider', 'details']);
        expect($orderDetailsFormatted['details'])->toBeArray()->toHaveKeys(['orderType', 'createdAt', 'scheduleDateInApproved', 'deliveryAddress', 'customer', 'payments']);
        expect($orderDetailsFormatted['details']['deliveryAddress'])->toBeArray()->toHaveKeys(['coordinates', 'formattedAddress', 'complement', 'neighborhood', 'city', 'state', 'reference']);
        expect($orderDetailsFormatted['details']['customer'])->toBeArray()->toHaveKeys(['name', 'phone']);
        expect($orderDetailsFormatted['details']['payments'])->toBeArray()->toHaveKeys(['pending']);
    });
});