<?php

use src\Aiqfome\Handlers\CheckMerchantInProviderHandler;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use Tests\Helpers\MockHelpers\RequestMockHelper;

use function Pest\Faker\fake;

beforeEach(function() {
    $this->redisService = $this->createMock(RedisService::class);
});

test('verifica se o merchant existe no provider', function() {
    $credentials = [
        'aiq-client-authorization' => fake()->name(),
        'aiq-user-agent' => fake()->name(),
    ];

    $accessToken = fake()->sha256();
    $this->redisService->method('hGet')->willReturn($accessToken);

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, ['data' => []]);
    $this->request = new Request(fake()->url());

    $this->handler = new CheckMerchantInProviderHandler($this->request, $this->redisService);
    $result = $this->handler->execute($credentials, fake()->uuid());

    expect($result)->toBe(true);
});

test('verifica se o merchant não existe no provider', function() {
    $credentials = [
        'aiq-client-authorization' => fake()->name(),
        'aiq-user-agent' => fake()->name(),
    ];

    $accessToken = fake()->sha256();
    $this->redisService->method('hGet')->willReturn($accessToken);

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_NOT_FOUND, ['data' => []]);
    $this->request = new Request(fake()->url());

    $this->handler = new CheckMerchantInProviderHandler($this->request, $this->redisService);
    $result = $this->handler->execute($credentials, fake()->uuid());

    expect($result)->toBe(false);
});
