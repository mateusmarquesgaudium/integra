<?php

use src\Neemo\Http\OrderDetails;
use src\geral\RedisService;
use src\Neemo\Enums\OrderStatus;
use src\geral\Enums\RequestConstants;
use src\Neemo\Enums\OrderType;
use Tests\Helpers\MockHelpers\RequestMockHelper;
use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);
    $this->merchantId = fake()->uuid();
    $this->orderId = fake()->uuid();
    $this->orderDetails = new OrderDetails($this->redisClient, $this->merchantId);
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
            ->willReturn(1001);

        $result = $this->orderDetails->checkRateLimit();
        expect($result)->toBeFalse();
    });
});

describe('orderStillPending', function () {
    test('pedido não encontrado (http code inválido)', function () {
        RequestMockHelper::createExternalMock(RequestConstants::HTTP_FORBIDDEN, []);

        $pipeline = [];
        $result = $this->orderDetails->orderStillPending($this->orderId, $this->merchantId, $pipeline);
        expect($result)->toBeTrue();
    });

    test('pedido encontrado (sem body)', function () {
        RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, []);

        $pipeline = [];
        $result = $this->orderDetails->orderStillPending($this->orderId, $this->merchantId, $pipeline);
        expect($result)->toBeTrue();
    });

    test('validação forma de entrega', function (int $formaEntrega) {
        RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
            'code' => RequestConstants::HTTP_OK,
            'Order' => [
                'status' => OrderStatus::NEW_ORDER,
                'forma_entrega' => $formaEntrega,
            ]
        ]);

        $pipeline = [];
        $result = $this->orderDetails->orderStillPending($this->orderId, $this->merchantId, $pipeline);
        expect($result)->toBeFalse();
    })->with([0, 2]);

    test('validação status do pedido', function (int $status) {
        RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
            'code' => RequestConstants::HTTP_OK,
            'Order' => [
                'status' => $status,
                'forma_entrega' => OrderType::DELIVERY,
            ]
        ]);

        $pipeline = [];
        $result = $this->orderDetails->orderStillPending($this->orderId, $this->merchantId, $pipeline);
        expect($result)->toBeTrue();
    })->with([OrderStatus::NEW_ORDER, OrderStatus::AWAITING_ONLINE_PAYMENT_APPROVAL]);

    test('validação status do pedido diferentes', function (int $status) {
        RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
            'code' => RequestConstants::HTTP_OK,
            'Order' => [
                'status' => $status,
                'forma_entrega' => OrderType::DELIVERY,
            ]
        ]);

        $pipeline = [];
        $result = $this->orderDetails->orderStillPending($this->orderId, $this->merchantId, $pipeline);
        expect($result)->toBeFalse();
    })->with([OrderStatus::SHIPPED, OrderStatus::DELIVERED]);

    test('pedido confirmado com is_scheduled', function () {
        RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
            'code' => RequestConstants::HTTP_OK,
            'Order' => [
                'id' => $this->orderId,
                'order_number' => fake()->numerify(),
                'payment_method' => fake()->randomElement(['credit_card', 'debit_card', 'pix']),
                'latitude' => fake()->latitude(),
                'longitude' => fake()->longitude(),
                'street' => fake()->streetName(),
                'number' => fake()->buildingNumber(),
                'complement' => fake()->sentence(),
                'neighborhood' => fake()->word(),
                'city' => fake()->city(),
                'uf' => fake()->state(),
                'reference_point' => fake()->sentence(),
                'name' => fake()->name(),
                'payment_online' => fake()->boolean(),
                'total' => fake()->randomFloat(2, 0, 100),
                'status' => OrderStatus::CONFIRMED,
                'forma_entrega' => OrderType::DELIVERY,
                'date' => fake()->date(),
                'scheduleDateInApproved' => fake()->date(),
                'is_scheduled' => true,
                'scheduled_at' => fake()->date(),
            ]
        ]);

        $pipeline = [];
        $result = $this->orderDetails->orderStillPending($this->orderId, $this->merchantId, $pipeline);
        expect($result)->toBeFalse();
        expect($pipeline)->toHaveCount(3);
    });

    test('pedido confirmado sem is_scheduled', function () {
        RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
            'code' => RequestConstants::HTTP_OK,
            'Order' => [
                'id' => $this->orderId,
                'order_number' => fake()->numerify(),
                'payment_method' => fake()->randomElement(['credit_card', 'debit_card', 'pix']),
                'latitude' => fake()->latitude(),
                'longitude' => fake()->longitude(),
                'street' => fake()->streetName(),
                'number' => fake()->buildingNumber(),
                'complement' => fake()->sentence(),
                'neighborhood' => fake()->word(),
                'city' => fake()->city(),
                'uf' => fake()->state(),
                'reference_point' => fake()->sentence(),
                'name' => fake()->name(),
                'payment_online' => fake()->boolean(),
                'total' => fake()->randomFloat(2, 0, 100),
                'status' => OrderStatus::CONFIRMED,
                'forma_entrega' => OrderType::DELIVERY,
                'date' => fake()->date(),
                'scheduleDateInApproved' => fake()->date(),
            ]
        ]);

        $pipeline = [];
        $result = $this->orderDetails->orderStillPending($this->orderId, $this->merchantId, $pipeline);
        expect($result)->toBeFalse();
        expect($pipeline)->toHaveCount(3);
    });
});

describe('checkNeedMonitorTheOrder', function () {
    test('pedido não encontrado', function () {
        RequestMockHelper::createExternalMock(RequestConstants::HTTP_FORBIDDEN, []);

        $pipeline = [];
        $result = $this->orderDetails->checkNeedMonitorTheOrder($this->orderId, $pipeline);
        expect($result)->toBeTrue();
    });

    test('pedido já confirmado', function (int $httpCode) {
        RequestMockHelper::createExternalMock($httpCode, [
            'code' => RequestConstants::HTTP_OK,
            'Order' => [
                'status' => OrderStatus::CONFIRMED,
            ]
        ]);

        $pipeline = [];
        $result = $this->orderDetails->checkNeedMonitorTheOrder($this->orderId, $pipeline);
        expect($result)->toBeTrue();
    })->with([
        RequestConstants::HTTP_OK,
        RequestConstants::HTTP_ACCEPTED,
        RequestConstants::HTTP_CREATED,
    ]);

    test('pedido despachado', function (int $httpCode) {
        RequestMockHelper::createExternalMock($httpCode, [
            'code' => RequestConstants::HTTP_OK,
            'Order' => [
                'status' => OrderStatus::SHIPPED,
            ]
        ]);

        $pipeline = [];
        $result = $this->orderDetails->checkNeedMonitorTheOrder($this->orderId, $pipeline);
        expect($result)->toBeFalse();
        expect($pipeline)->toHaveCount(1);
    })->with([
        RequestConstants::HTTP_OK,
        RequestConstants::HTTP_ACCEPTED,
        RequestConstants::HTTP_CREATED,
    ]);

    test('pedido entregue', function (int $httpCode) {
        RequestMockHelper::createExternalMock($httpCode, [
            'code' => RequestConstants::HTTP_OK,
            'Order' => [
                'status' => OrderStatus::DELIVERED,
            ],
        ]);

        $pipeline = [];
        $result = $this->orderDetails->checkNeedMonitorTheOrder($this->orderId, $pipeline);
        expect($result)->toBeFalse();
        expect($pipeline)->toHaveCount(0);
    })->with([
        RequestConstants::HTTP_OK,
        RequestConstants::HTTP_ACCEPTED,
        RequestConstants::HTTP_CREATED,
    ]);
});
