<?php

use src\ifood\Http\OrderDetails;
use src\geral\RedisService;
use src\ifood\Enums\OrderStatus;
use src\geral\Enums\RequestConstants;
use src\ifood\Enums\OrderEntityType;
use src\ifood\Http\Oauth;
use Tests\Helpers\MockHelpers\RequestMockHelper;
use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);
    $this->oauth = $this->createMock(Oauth::class);
    $this->oauth->method('getHeadersRequests')->willReturn([]);

    $this->orderId = fake()->uuid();
    $this->orderDetails = new OrderDetails($this->oauth, $this->redisClient);
});

afterEach(function () {
    Mockery::close();
});

describe('checkRateLimit', function () {
    test('taxa dentro do limite (ratelimit)', function () {
        $this->redisClient
            ->method('zRemRangeByScore')
            ->willReturn(1);
        $this->redisClient
            ->method('zCard')
            ->willReturn(100);
        $this->redisClient
            ->method('zAdd')
            ->willReturn(1);

        $result = $this->orderDetails->checkRateLimit();
        expect($result)->toBeTrue();
    });

    test('taxa excede o limite (ratelimit)', function () {
        $this->redisClient
            ->method('zRemRangeByScore')
            ->willReturn(1);
        $this->redisClient
            ->method('zCard')
            ->willReturn(3001);

        $result = $this->orderDetails->checkRateLimit();
        expect($result)->toBeFalse();
    });
});

describe('searchDetails', function () {
    test('erro diferente de HTTO_OK', function () {
        RequestMockHelper::createExternalMock(RequestConstants::HTTP_FORBIDDEN, []);

        $result = $this->orderDetails->searchDetails($this->orderId);
        expect($result)->toBeArray();
        expect($result)->toBeEmpty();
    });

    test('resposta sem o id do pedido', function () {
        RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, []);

        $result = $this->orderDetails->searchDetails($this->orderId);
        expect($result)->toBeArray();
        expect($result)->toBeEmpty();
    });

    test('resposta com o id do pedido', function () {
        RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
            'id' => $this->orderId,
        ]);

        $result = $this->orderDetails->searchDetails($this->orderId);
        expect($result)->toBeArray();
        expect($result)->toHaveKey('id');
        expect($result['id'])->toBe($this->orderId);
    });
});

describe('formatOrder', function () {
    test('formatar pedido', function ($isTest) {
        $orderDetails = [
            'id' => $this->orderId,
            'displayId' => fake()->uuid(),
            'merchant' => [
                'id' => fake()->uuid(),
            ],
            'orderType' => OrderEntityType::DELIVERY,
            'delivery' => [
                'deliveredBy' => OrderEntityType::MERCHANT,
                'createdAt' => fake()->date('Y-m-d\TH:i:s\Z'),
                'deliveryAddress' => [
                    'streetName' => fake()->streetName(),
                    'streetNumber' => fake()->buildingNumber(),
                    'formattedAddress' => fake()->address(),
                    'neighborhood' => fake()->streetSuffix(),
                    'postalCode' => fake()->postcode(),
                    'city' => fake()->city(),
                    'state' => fake()->state(),
                    'country' => fake()->country(),
                    'coordinates' => [
                        'latitude' => fake()->latitude(),
                        'longitude' => fake()->longitude(),
                    ],
                ],
            ],
            'customer' => [
                'name' => fake()->name(),
                'phone' => [
                    'number' => fake()->phoneNumber(),
                    'localizer' => fake()->buildingNumber(),
                ],
            ],
            'payments' => [
                'pending' => fake()->randomDigit(),
            ],
            'isTest' => $isTest,
            'createdAt' => fake()->date('Y-m-d\TH:i:s\Z'),
        ];

        $result = $this->orderDetails->formatOrder($orderDetails);
        expect($result)->toBeArray();
        expect($result)->toHaveKey('orderId');
        expect($result)->toHaveKey('displayId');
        expect($result['orderId'])->toBe($orderDetails['id']);
        expect($result['displayId'])->toBe($orderDetails['displayId']);
    })->with([
        'isTestTrue' => true,
        'isTestFalse' => false,
    ]);
});

describe('filterOrderAvailable', function () {
    test('pedido não é delivery', function () {
        $orderDetails = [
            'details' => [
                'orderType' => 'PICKUP',
            ]
        ];

        $result = $this->orderDetails->filterOrderAvailable($orderDetails);
        expect($result)->toBeFalse();
    });

    test('entrega não é do merchant', function () {
        $orderDetails = [
            'details' => [
                'orderType' => OrderEntityType::DELIVERY,
                'deliveredBy' => 'IFOOD',
            ]
        ];

        $result = $this->orderDetails->filterOrderAvailable($orderDetails);
        expect($result)->toBeFalse();
    });

    test('pedido disponível', function () {
        $orderDetails = [
            'details' => [
                'orderType' => OrderEntityType::DELIVERY,
                'deliveredBy' => OrderEntityType::MERCHANT,
            ]
        ];

        $result = $this->orderDetails->filterOrderAvailable($orderDetails);
        expect($result)->toBeTrue();
    });
});