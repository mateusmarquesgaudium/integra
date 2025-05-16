<?php

use src\Aiqfome\Http\RefreshToken;
use src\geral\Custom;
use src\geral\RedisService;
use src\geral\Request;
use Tests\Helpers\MockHelpers\RequestMockHelper;

use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisService = $this->createMock(RedisService::class);
    $this->custom = $this->createMock(Custom::class);
});

test('deve ser possível adicionar headers adicionais', function () {
    $accessToken = fake()->sha256();

    RequestMockHelper::createExternalMock(200, ['data' => []]);
    $this->request = new Request(fake()->url());

    $refreshToken = new RefreshToken($this->request, $this->custom, $this->redisService);
    $refreshToken->setAdditionalHeaders($accessToken);

    // Verifique se o header foi adicionado
    $reflection = new ReflectionClass($refreshToken);
    $method = $reflection->getMethod('getAdditionalHeaders');
    $method->setAccessible(true);
    $result = $method->invoke($refreshToken);

    expect($result)->toContain('RefreshToken: ' . $accessToken);
});


test('deve retornar os headers adicionais', function () {
    $accessToken = fake()->sha256();

    $this->redisService->method('hGet')->willReturn($accessToken);

    RequestMockHelper::createExternalMock(200, ['data' => []]);
    $this->request = new Request(fake()->url());

    $refreshToken = new RefreshToken($this->request, $this->custom, $this->redisService);
    $refreshToken->setAdditionalHeaders($accessToken);
    $reflection = new ReflectionClass($refreshToken);
    $method = $reflection->getMethod('getAdditionalHeaders');
    $method->setAccessible(true);

    $result = $method->invoke($refreshToken);

    // Verifique se o result tem a key RefreshToken
    expect($result)->toContain('RefreshToken: ' . $accessToken);
});
