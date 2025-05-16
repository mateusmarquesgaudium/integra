<?php

use src\AnotaAi\Http\OrderDetails;
use src\geral\RedisService;
use src\AnotaAi\Enums\OrderStatus;
use src\geral\Enums\RequestConstants;
use Tests\Helpers\MockHelpers\RequestMockHelper;
use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);
    $this->merchantId = fake()->uuid();
    $this->orderId = fake()->uuid();
    $this->authorizationToken = fake()->sha256();
    $this->orderDetails = new OrderDetails($this->redisClient, $this->merchantId, $this->authorizationToken);
});

afterEach(function () {
    Mockery::close();
});

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
        ->willReturn(501);

    $result = $this->orderDetails->checkRateLimit();
    expect($result)->toBeFalse();
});

test('pedido programado não pendente', function () {
    $orderEvent = [
        'merchant_id' => $this->merchantId,
        'event_details' => [
            'details' => [
                'scheduleDateInApproved' => fake()->date('Y-m-d\TH:i:s\Z'),
            ],
        ],
    ];

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
        'info' => [
            'check' => OrderStatus::APPOINTMENT_ACCEPTED,
        ],
    ]);
    $orderDetails = new OrderDetails($this->redisClient, $this->merchantId, $this->authorizationToken);

    $pipeline = [];
    $result = $orderDetails->orderStillPending($this->orderId, $orderEvent, $pipeline);

    expect($result)->toBeFalse();
    expect($pipeline)->toHaveCount(3);
});

test('pedido não programado não pendente', function () {
    $orderEvent = [
        'merchant_id' => $this->merchantId,
    ];

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
        'info' => [
            'check' => OrderStatus::APPOINTMENT_ACCEPTED,
        ],
    ]);
    $orderDetails = new OrderDetails($this->redisClient, $this->merchantId, $this->authorizationToken);

    $pipeline = [];
    $result = $orderDetails->orderStillPending($this->orderId, $orderEvent, $pipeline);

    expect($result)->toBeFalse();
    expect($pipeline)->toHaveCount(3);
});

test('pedido não em produção ou cancelamento', function () {
    $orderEvent = [];

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
        'info' => [
            'check' => OrderStatus::READY,
        ],
    ]);
    $orderDetails = new OrderDetails($this->redisClient, $this->merchantId, $this->authorizationToken);

    $pipeline = [];
    $result = $orderDetails->orderStillPending($this->orderId, $orderEvent, $pipeline);

    expect($result)->toBeFalse();
});

test('pedido em revisão', function () {
    $orderEvent = [];

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
        'info' => [
            'check' => OrderStatus::IN_REVIEW,
        ],
    ]);
    $orderDetails = new OrderDetails($this->redisClient, $this->merchantId, $this->authorizationToken);

    $pipeline = [];
    $result = $orderDetails->orderStillPending($this->orderId, $orderEvent, $pipeline);

    expect($result)->toBeTrue();
});

test('pedido pendente com erro API', function () {
    $orderEvent = [];

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, []);
    $orderDetails = new OrderDetails($this->redisClient, $this->merchantId, $this->authorizationToken);

    $pipeline = [];
    $result = $orderDetails->orderStillPending($this->orderId, $orderEvent, $pipeline);

    expect($result)->toBeTrue();
});

test('pedido pendente com resposta não HTTP_OK', function () {
    $orderEvent = [];

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_FORBIDDEN, []);
    $orderDetails = new OrderDetails($this->redisClient, $this->merchantId, $this->authorizationToken);

    $pipeline = [];
    $result = $orderDetails->orderStillPending($this->orderId, $orderEvent, $pipeline);

    expect($result)->toBeTrue();
});

test('pedido monitorado com erro API', function () {
    $orderEvent = [];

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, []);
    $orderDetails = new OrderDetails($this->redisClient, $this->merchantId, $this->authorizationToken);

    $pipeline = [];
    $result = $orderDetails->checkNeedMonitorTheOrder($this->orderId, $orderEvent, $pipeline);

    expect($result)->toBeTrue();
});

test('pedido em produção monitorado', function () {
    $orderEvent = [];

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
        'info' => [
            'check' => OrderStatus::IN_PRODUCTION,
        ],
    ]);
    $orderDetails = new OrderDetails($this->redisClient, $this->merchantId, $this->authorizationToken);

    $pipeline = [];
    $result = $orderDetails->checkNeedMonitorTheOrder($this->orderId, $orderEvent, $pipeline);

    expect($result)->toBeTrue();
});

test('pedido pronto para entrega não monitorado', function () {
    $orderEvent = [
        'order_id' => $this->orderId,
    ];

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
        'info' => [
            'check' => OrderStatus::READY,
        ],
    ]);
    $orderDetails = new OrderDetails($this->redisClient, $this->merchantId, $this->authorizationToken);

    $pipeline = [];
    $result = $orderDetails->checkNeedMonitorTheOrder($this->orderId, $orderEvent, $pipeline);

    expect($result)->toBeFalse();
    expect($pipeline)->toHaveCount(1);
});

test('outros status não monitorados', function () {
    $orderEvent = [];

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
        'info' => [
            'check' => OrderStatus::IN_REVIEW,
        ],
    ]);
    $orderDetails = new OrderDetails($this->redisClient, $this->merchantId, $this->authorizationToken);

    $pipeline = [];
    $result = $orderDetails->checkNeedMonitorTheOrder($this->orderId, $orderEvent, $pipeline);

    expect($result)->toBeFalse();
    expect($pipeline)->toHaveCount(0);
});