<?php

use src\Delivery\Enums\Provider;
use src\geral\Custom;
use src\geral\RedisService;
use src\geral\CustomException;
use src\geral\Enums\RequestConstants;
use src\Opendelivery\Enums\DeliveryStatus;
use src\Opendelivery\Handlers\LogisticsHandler;
use Tests\Helpers\MockHelpers\RequestMockHelper;

use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);
    $this->custom = new Custom();

    $this->provider = Provider::SAIPOS;

    $this->logisticsHandler = new LogisticsHandler($this->redisClient, $this->custom, $this->provider);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_REQUEST['orderId'] = fake()->uuid();
});

afterEach(function () {
    Mockery::close();
});

test('pedido não encontrado e detalhes vazio', function () {
    $this->expectException(CustomException::class);
    $this->expectExceptionMessage('The requested resource was not found');

    $this->redisClient->expects($this->once())->method('get')->willReturn(json_encode([]));
    RequestMockHelper::createExternalMock(RequestConstants::HTTP_NOT_FOUND, []);

    $this->logisticsHandler->getDelivery();
});

test('pedido não encontrado e lastEvent PENDING', function () {
    $orderDetails = [
        'lastEvent' => DeliveryStatus::PENDING,
    ];

    $this->redisClient->expects($this->once())->method('get')->willReturn(json_encode($orderDetails));
    RequestMockHelper::createExternalMock(RequestConstants::HTTP_NOT_FOUND, []);

    $result = $this->logisticsHandler->getDelivery();
    expect($result)->toBe($orderDetails);
});

test('pedido encontrado', function () {
    $orderDetails = [
        'lastEvent' => DeliveryStatus::ORDER_PICKED,
        'events' => [],
    ];

    $this->redisClient->expects($this->once())->method('get')->willReturn(json_encode($orderDetails));
    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, $orderDetails);

    $result = $this->logisticsHandler->getDelivery();
    expect($result)->toBe($orderDetails);
});
