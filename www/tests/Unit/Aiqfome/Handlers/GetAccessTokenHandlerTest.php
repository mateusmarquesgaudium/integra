<?php

use src\Aiqfome\Enums\RedisSchema;
use src\Aiqfome\Handlers\GetAccessTokenHandler;
use src\Cache\Enums\CacheKeys;
use src\Delivery\Enums\Provider;
use function Pest\Faker\fake;

beforeEach(function () {
    /** @var RedisService */
    $this->redisService = Mockery::mock('src\geral\RedisService');
    $this->getAccessTokenHandler = new GetAccessTokenHandler($this->redisService);
});

test('deve retornar o access token', function () {
    $accessToken = fake()->sha256();

    $this->redisService->shouldReceive('hGet')->andReturn($accessToken);

    $result = $this->getAccessTokenHandler->execute(fake()->uuid(), Provider::AIQFOME);

    expect($result)->toBe($accessToken);
});

test('deve lançar uma exceção ao não encontrar a empresa do merchant', function () {
    $this->redisService->shouldReceive('hGet')->andReturn(null);

    $this->getAccessTokenHandler->execute(fake()->uuid(), Provider::AIQFOME);
})->throws(\Exception::class);

test('deve lançar uma exceção ao não encontrar o access token', function () {
    $merchantId = fake()->uuid();
    $key = str_replace(['{provider}', '{merchantId}'], [Provider::AIQFOME, $merchantId], CacheKeys::PROVIDER_MERCHANTS_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($key, 'enterpriseId')->andReturn(1);

    $key = str_replace('{enterprise_id}', 1, RedisSchema::KEY_AUTHENTICATE_AIQFOME);
    $this->redisService->shouldReceive('hGet')->once()->with($key, 'access_token')->andReturn(null);

    $this->getAccessTokenHandler->execute($merchantId, Provider::AIQFOME);
})->throws(\Exception::class);
