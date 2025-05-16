<?php

use src\Aiqfome\Http\AuthenticateToken;
use src\geral\Custom;
use src\geral\RedisService;
use src\geral\Request;
use Tests\Helpers\MockHelpers\RequestMockHelper;

use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisService = $this->createMock(RedisService::class);
    $this->custom = $this->createMock(Custom::class);
});

test('deve retornar os headers adicionais', function () {
    $accessToken = fake()->sha256();

    $this->redisService->method('hGet')->willReturn($accessToken);

    RequestMockHelper::createExternalMock(200, ['data' => []]);
    $this->request = new Request(fake()->url());

    $authenticateToken = new AuthenticateToken($this->request, $this->custom, $this->redisService);
    $reflection = new ReflectionClass($authenticateToken);
    $method = $reflection->getMethod('getAdditionalHeaders');
    $method->setAccessible(true);

    $result = $method->invoke($authenticateToken);

    expect($result)->toBe([]);
});

test('deve lançar exception ao chamar o método setAdditionalHeaders', function () {
    $accessToken = fake()->sha256();

    $this->redisService->method('hGet')->willReturn($accessToken);

    RequestMockHelper::createExternalMock(200, ['data' => []]);
    $this->request = new Request(fake()->url());

    $authenticateToken = new AuthenticateToken($this->request, $this->custom, $this->redisService);
    $authenticateToken->setAdditionalHeaders(fake()->name());
})->throws(\BadMethodCallException::class);
