<?php

use src\Aiqfome\Http\OrderFinished;
use src\geral\Custom;
use src\geral\RedisService;
use Tests\Helpers\MockHelpers\RequestMockHelper;

use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisService = $this->createMock(RedisService::class);
    $this->custom = $this->createMock(Custom::class);
});

test('envia evento de pedido finalizado', function() {
    $custom = [
        'url' => fake()->url(),
        'credentials' => [
            'aiq-client-authorization' => fake()->name(),
            'aiq-user-agent' => fake()->name(),
        ],
    ];

    $event = [
        'orderId' => fake()->uuid(),
        'merchantId' => fake()->uuid()
    ];

    $accessToken = fake()->sha256();
    $this->redisService->method('hGet')->willReturn($accessToken);
    $this->custom
        ->method('getParams')
        ->willReturn($custom);
    $this->orderInTransit = new OrderFinished($this->redisService, $this->custom);

    RequestMockHelper::createExternalMock(200, ['data' => []]);
    $pipeline = [];
    $result = $this->orderInTransit->send($event, $pipeline);

    expect($result)->toBeTrue();
});

test('deve retornar falso se orderId não for informado', function() {
    $event = [];
    $this->orderInTransit = new OrderFinished($this->redisService, $this->custom);
    $pipeline = [];
    $result = $this->orderInTransit->send($event, $pipeline);

    expect($result)->toBeFalse();
});

test('deve retornar falso se a resposta não for 200 ou 204', function() {
    $custom = [
        'url' => fake()->url(),
        'credentials' => [
            'aiq-client-authorization' => fake()->name(),
            'aiq-user-agent' => fake()->name(),
        ],
    ];

    $event = [
        'orderId' => fake()->uuid(),
    ];

    $accessToken = fake()->sha256();
    $this->redisService->method('hGet')->willReturn($accessToken);
    $this->custom
        ->method('getParams')
        ->willReturn($custom);
    $this->orderInTransit = new OrderFinished($this->redisService, $this->custom);

    RequestMockHelper::createExternalMock(500, ['data' => []]);
    $pipeline = [];
    $result = $this->orderInTransit->send($event, $pipeline);

    expect($result)->toBeFalse();
});

test('deve retornar falso se o tempo de retentativa não for alcançado', function() {
    $custom = [
        'url' => fake()->url(),
        'credentials' => [
            'aiq-client-authorization' => fake()->name(),
            'aiq-user-agent' => fake()->name(),
        ],
    ];

    $event = [
        'orderId' => fake()->uuid(),
        'merchantId' => fake()->uuid(),
        'timeToRetry' => time() + 100
    ];

    $accessToken = fake()->sha256();
    $this->redisService->method('hGet')->willReturn($accessToken);
    $this->custom
        ->method('getParams')
        ->willReturn($custom);
    $this->orderInTransit = new OrderFinished($this->redisService, $this->custom);

    $pipeline = [];
    $result = $this->orderInTransit->send($event, $pipeline);

    expect($result)->toBeFalse();
});

test('deve retornar falso se a empresa estiver na fila de refresh do token', function() {
    $custom = [
        'url' => fake()->url(),
        'credentials' => [
            'aiq-client-authorization' => fake()->name(),
            'aiq-user-agent' => fake()->name(),
        ],
    ];

    $event = [
        'orderId' => fake()->uuid(),
        'merchantId' => fake()->uuid(),
    ];

    $accessToken = fake()->sha256();
    $this->redisService->method('hGet')->willReturn($accessToken);
    $this->custom
        ->method('getParams')
        ->willReturn($custom);
    $this->orderInTransit = new OrderFinished($this->redisService, $this->custom);

    $this->redisService->method('sIsMember')->willReturn(true);
    $pipeline = [];
    $result = $this->orderInTransit->send($event, $pipeline);

    expect($result)->toBeFalse();
});

test('aiqfome retorna 401 e deve retornar falso', function() {
    $custom = [
        'url' => fake()->url(),
        'timeToRetry' => 10,
        'credentials' => [
            'aiq-client-authorization' => fake()->name(),
            'aiq-user-agent' => fake()->name(),
        ],
    ];

    $event = [
        'orderId' => fake()->uuid(),
        'merchantId' => fake()->uuid(),
    ];

    $accessToken = fake()->sha256();
    $this->redisService->method('hGet')->willReturn($accessToken);
    $this->custom
        ->method('getParams')
        ->willReturn($custom);
    $this->orderInTransit = new OrderFinished($this->redisService, $this->custom);

    RequestMockHelper::createExternalMock(401, ['data' => []]);
    $pipeline = [];
    $result = $this->orderInTransit->send($event, $pipeline);

    expect($result)->toBeFalse();
});
