<?php

use src\Aiqfome\Http\GetOrderDetails;
use src\geral\Custom;
use src\geral\RedisService;
use Tests\Helpers\MockHelpers\RequestMockHelper;

use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisService = $this->createMock(RedisService::class);
    $this->custom = $this->createMock(Custom::class);
});

test('obtém os detalhes dos pedidos', function() {
    $custom = [
        'url' => fake()->url(),
        'maxOrdersAtATime' => 1,
        'credentials' => [
            'aiq-client-authorization' => fake()->name(),
            'aiq-user-agent' => fake()->name(),
        ],
    ];

    // Simulando um array indexado de eventos
    $events = [
        json_encode(['order_id' => fake()->uuid()]),
    ];

    $accessToken = fake()->sha256();
    $this->redisService->method('lRange')->willReturn($events);
    $this->redisService->method('hGet')->willReturn($accessToken);
    $this->custom
        ->method('getParams')
        ->willReturn($custom);
    $this->redisService->expects($this->atLeastOnce())
        ->method('pipelineCommands')
        ->with($this->isType('array'));
    $this->getOrderDetails = new GetOrderDetails($this->custom, $this->redisService);

    RequestMockHelper::createExternalMock(200, ['data' => []]);
    $this->getOrderDetails->execute();
});

test('deve retornar uma UnderflowException se não possuir pedidos', function() {
    $events = [];
    $custom = [
        'url' => fake()->url(),
        'maxOrdersAtATime' => 1,
        'credentials' => [
            'aiq-client-authorization' => fake()->name(),
            'aiq-user-agent' => fake()->name(),
        ],
    ];
    $this->custom
        ->method('getParams')
        ->willReturn($custom);
    $this->redisService->method('lRange')->willReturn($events);
    $this->getOrderDetails = new GetOrderDetails($this->custom, $this->redisService);
    $this->getOrderDetails->execute();
})->throws(\UnderflowException::class);
