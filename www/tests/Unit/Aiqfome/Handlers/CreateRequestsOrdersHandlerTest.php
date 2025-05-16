<?php

use src\Aiqfome\Enums\RedisSchema;
use src\Aiqfome\Handlers\CreateRequestsOrdersHandler;
use src\Delivery\Enums\Provider;
use src\geral\Custom;
use Tests\Helpers\MockHelpers\RequestMockHelper;

use function Pest\Faker\fake;

beforeEach(function() {
    $this->custom = $this->createMock(Custom::class);
    /** @var RedisService */
    $this->redisService = Mockery::mock('src\geral\RedisService');
    $this->accessToken = fake()->sha256();

    $this->custom->method('getParams')->willReturn([
        'credentials' => [
            'aiq-client-authorization' => fake()->name(),
            'aiq-user-agent' => fake()->name(),
        ],
        'maxAttemptsRequest' => 3,
        'timeToRetry' => 10,
    ]);

    $this->handler = new CreateRequestsOrdersHandler($this->custom, $this->redisService);
});

test('cria requisição para o pedido', function() {
    $orders = [
        json_encode(['provider' => Provider::AIQFOME, 'order_id' => fake()->uuid(), 'merchant_id' => fake()->uuid()]),
        json_encode(['provider' => Provider::AIQFOME, 'order_id' => fake()->uuid(), 'merchant_id' => fake()->uuid()]),
        json_encode(['provider' => Provider::AIQFOME, 'order_id' => fake()->uuid(), 'merchant_id' => fake()->uuid()]),
    ];

    $pipeline = [];
    $url = fake()->url();

    $this->redisService->shouldReceive('sIsMember')->andReturn(false);
    $this->redisService->shouldReceive('hGet')->andReturn($this->accessToken);
    $this->redisService->shouldReceive('pipelineCommands')->andReturn([]);

    RequestMockHelper::createExternalMock(200, ['data' => []]);
    $result = $this->handler->execute($url, $orders);

    expect(count($result))->toBe(3);
});

test('cria a requisição para o pedido, mas a um pedido que ainda não está no tempo de retry', function() {
    $orders = [
        json_encode(['provider' => Provider::AIQFOME, 'order_id' => fake()->uuid(), 'merchant_id' => fake()->uuid(), 'nextTimeToRetry' => strtotime('+1 hour')]),
        json_encode(['provider' => Provider::AIQFOME, 'order_id' => fake()->uuid(), 'merchant_id' => fake()->uuid()]),
        json_encode(['provider' => Provider::AIQFOME, 'order_id' => fake()->uuid(), 'merchant_id' => fake()->uuid()]),
    ];

    $pipeline = [];
    $url = fake()->url();

    $this->redisService->shouldReceive('sIsMember')->andReturn(false);
    $this->redisService->shouldReceive('hGet')->andReturn($this->accessToken);
    $this->redisService->shouldReceive('pipelineCommands')->andReturn([]);

    RequestMockHelper::createExternalMock(200, ['data' => []]);
    $result = $this->handler->execute($url, $orders);

    expect(count($result))->toBe(2);
});

test('cria a requisição para o pedido, mas a um pedido com o provider vazio', function() {
    $orders = [
        json_encode(['provider' => Provider::AIQFOME, 'order_id' => fake()->uuid(), 'merchant_id' => fake()->uuid()]),
        json_encode(['provider' => '', 'order_id' => fake()->uuid(), 'merchant_id' => fake()->uuid()]),
        json_encode(['provider' => Provider::AIQFOME, 'order_id' => fake()->uuid(), 'merchant_id' => fake()->uuid()]),
    ];

    $pipeline = [];
    $url = fake()->url();

    $this->redisService->shouldReceive('sIsMember')->andReturn(false);
    $this->redisService->shouldReceive('hGet')->andReturn($this->accessToken);
    $this->redisService->shouldReceive('pipelineCommands')->andReturn([]);

    RequestMockHelper::createExternalMock(200, ['data' => []]);
    $result = $this->handler->execute($url, $orders, $pipeline);

    expect(count($result))->toBe(2);
});

test('não deve criar um pedido para um merchant na lista de refresh token', function() {
    $orders = [
        json_encode(['provider' => Provider::AIQFOME, 'order_id' => fake()->uuid(), 'merchant_id' => fake()->uuid()]),
        json_encode(['provider' => Provider::AIQFOME, 'order_id' => fake()->uuid(), 'merchant_id' => fake()->uuid()]),
        json_encode(['provider' => Provider::AIQFOME, 'order_id' => fake()->uuid(), 'merchant_id' => fake()->uuid()]),
    ];

    $pipeline = [];
    $url = fake()->url();

    $this->redisService->shouldReceive('sIsMember')->andReturn(true);
    $this->redisService->shouldReceive('hGet')->andReturn($this->accessToken);
    $this->redisService->shouldReceive('pipelineCommands')->andReturn([]);

    RequestMockHelper::createExternalMock(200, ['data' => []]);
    $result = $this->handler->execute($url, $orders, $pipeline);

    expect(count($result))->toBe(0);
});

test('não deve criar um pedido para um merchant na lista de integrações inválidas', function() {
    $merchantId = fake()->uuid();
    $orders = [
        json_encode(['provider' => Provider::AIQFOME, 'order_id' => fake()->uuid(), 'merchant_id' => $merchantId]),
    ];

    $pipeline = [];
    $url = fake()->url();

    $this->redisService->shouldReceive('sIsMember')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH, $merchantId)->andReturn(false);
    $this->redisService->shouldReceive('sIsMember')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS, $merchantId)->andReturn(true);
    $this->redisService->shouldReceive('hGet')->andReturn($this->accessToken);
    $this->redisService->shouldReceive('pipelineCommands')->andReturn([]);

    RequestMockHelper::createExternalMock(200, ['data' => []]);
    $result = $this->handler->execute($url, $orders, $pipeline);

    expect(count($result))->toBe(0);
});

test('o pedido ter o máximo de tentativas', function() {
    $orders = [
        json_encode(['provider' => Provider::AIQFOME, 'order_id' => fake()->uuid(), 'merchant_id' => fake()->uuid(), 'attempts' => 5]),
    ];

    $pipeline = [];
    $url = fake()->url();

    $this->redisService->shouldReceive('sIsMember')->andReturn(false);
    $this->redisService->shouldReceive('hGet')->andReturn($this->accessToken);
    $this->redisService->shouldReceive('pipelineCommands')->andReturn([]);

    RequestMockHelper::createExternalMock(200, ['data' => []]);
    $result = $this->handler->execute($url, $orders, $pipeline);

    expect(count($result))->toBe(0);
});

test('não existir o access token para o merchant', function() {
    $merchantId = fake()->uuid();
    $orders = [
        json_encode(['provider' => Provider::AIQFOME, 'order_id' => fake()->uuid(), 'merchant_id' => $merchantId]),
        json_encode(['provider' => Provider::AIQFOME, 'order_id' => fake()->uuid(), 'merchant_id' => $merchantId]),
    ];

    $pipeline = [];
    $url = fake()->url();

    $this->redisService->shouldReceive('sIsMember')->andReturn(false);
    $this->redisService->shouldReceive('hGet')->andReturn(null);
    $this->redisService->shouldReceive('pipelineCommands')->andReturn([]);

    RequestMockHelper::createExternalMock(200, ['data' => []]);
    $result = $this->handler->execute($url, $orders, $pipeline);

    expect(count($result))->toBe(0);
});
