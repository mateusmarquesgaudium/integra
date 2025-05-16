<?php

use src\Aiqfome\Enums\RedisSchema;
use src\Aiqfome\Handlers\MerchantsRefreshTokenHandler;
use src\Aiqfome\Handlers\RefreshTokenHandler;
use src\Cache\Enums\CacheKeys;
use src\Delivery\Enums\Provider;
use src\geral\Custom;
use Tests\Helpers\MockHelpers\RequestMockHelper;

use function Pest\Faker\fake;

beforeEach(function () {
    /** @var RedisService */
    $this->redisService = Mockery::mock('src\geral\RedisService');
    $this->merchantsRefreshTokenHandler = new MerchantsRefreshTokenHandler($this->redisService);

    $this->custom = $this->createMock(Custom::class);
    $this->refreshTokenHandler = new RefreshTokenHandler($this->custom, $this->redisService, $this->merchantsRefreshTokenHandler);
});

test('deve atualizar o token do merchant', function() {

    $this->custom
        ->method('getParams')
        ->willReturn([
            'url' => fake()->url(),
            'credentials' => [
                'aiq-client-authorization' => fake()->name(),
                'aiq-user-agent' => fake()->name(),
                'client_secret' => fake()->sha256(),
                'client_id' => fake()->uuid(),
            ],
        ]);

    $merchantId = fake()->uuid();
    $this->redisService->shouldReceive('sMembers')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH)->andReturn([$merchantId]);
    $this->redisService->shouldReceive('sIsMember')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS, $merchantId)->andReturn(false);
    $this->redisService->shouldReceive('hGet')->andReturn(true);
    $this->redisService->shouldReceive('hMSet')->andReturn(true);
    $this->redisService->shouldReceive('expire')->andReturn(true);
    $this->redisService->shouldReceive('pipelineCommands')->andReturn([]);
    $this->redisService->shouldReceive('get')->andReturn('token');
    $this->redisService->shouldReceive('set')->andReturn(true);

    RequestMockHelper::createExternalMock(200, ['data' => [
        'expires_in' => 3600,
    ]]);

    $this->refreshTokenHandler->handle(1);
});

test('deve atualizar o token a partir do refresh token', function() {
        $this->custom
            ->method('getParams')
            ->willReturn([
                'url' => fake()->url(),
                'credentials' => [
                    'aiq-client-authorization' => fake()->name(),
                    'aiq-user-agent' => fake()->name(),
                    'client_secret' => fake()->sha256(),
                    'client_id' => fake()->uuid(),
                ],
            ]);

        $merchantId = fake()->uuid();
        $this->redisService->shouldReceive('sMembers')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH)->andReturn([$merchantId]);
        $this->redisService->shouldReceive('sIsMember')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS, $merchantId)->andReturn(false);
        $this->redisService->shouldReceive('hGet')->andReturn(true);
        $this->redisService->shouldReceive('hMSet')->andReturn(true);
        $this->redisService->shouldReceive('expire')->andReturn(true);
        $this->redisService->shouldReceive('pipelineCommands')->andReturn([]);
        $this->redisService->shouldReceive('get')->andReturn('token');
        $this->redisService->shouldReceive('set')->andReturn(true);

        RequestMockHelper::createExternalMock(200, ['data' => [
            'expires_in' => 3600,
        ]]);

        $this->refreshTokenHandler->handle(1);
});

test('integracao aiqfome retorna 401 e existe mais de um pedido para o mesmo provider', function(){
    $this->custom
        ->method('getParams')
        ->willReturn([
            'url' => fake()->url(),
            'max_attempts_refresh_token' => 1,
            'credentials' => [
                'aiq-client-authorization' => fake()->name(),
                'aiq-user-agent' => fake()->name(),
                'client_secret' => fake()->sha256(),
                'client_id' => fake()->uuid(),
            ],
        ]);

    $merchantId = fake()->uuid();
    $key = str_replace('{merchant_id}', $merchantId, RedisSchema::KEY_AUTHENTICATE_AIQFOME_ERR_COUNT_REFRESH_MERCHANT);
    $this->redisService->shouldReceive('sMembers')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH)->andReturn([$merchantId, $merchantId]);
    $this->redisService->shouldReceive('sIsMember')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS, $merchantId)->andReturn(false);
    $this->redisService->shouldReceive('hGet')->andReturn(true);
    $this->redisService->shouldReceive('hMSet')->andReturn(true);
    $this->redisService->shouldReceive('expire')->andReturn(true);
    $this->redisService->shouldReceive('pipelineCommands')->andReturn([]);
    $this->redisService->shouldReceive('get')->once()->with($key)->andReturn(1);
    $this->redisService->shouldReceive('set')->andReturn(true);

    RequestMockHelper::createExternalMock(401, ['data' => [
        'error' => 'invalid_grant',
        'error_description' => 'The refresh token is invalid',
    ]]);

    $this->refreshTokenHandler->handle(1);
});

test('deve não ser capaz de atualizar se não houver username ou password', function() {
    $this->custom
        ->method('getParams')
        ->willReturn([
            'url' => fake()->url(),
            'credentials' => [
                'aiq-client-authorization' => fake()->name(),
                'aiq-user-agent' => fake()->name(),
                'client_secret' => fake()->sha256(),
                'client_id' => fake()->uuid(),
            ],
        ]);

    $merchantId = fake()->uuid();
    $this->redisService->shouldReceive('sMembers')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH)->andReturn([$merchantId]);
    $this->redisService->shouldReceive('sIsMember')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS, $merchantId)->andReturn(false);
    $merchantCredentialsKey = str_replace(['{provider}', '{merchantId}'], [Provider::AIQFOME, $merchantId], CacheKeys::PROVIDER_MERCHANTS_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($merchantCredentialsKey, 'enterpriseId')->andReturn(1);
    $this->redisService->shouldReceive('hGet')->once()->with($merchantCredentialsKey, 'enterpriseIntegrationId')->andReturn(1);

    $integrationMerchantKey = str_replace('{integrationId}', 1, CacheKeys::ENTERPRISE_INTEGRATION_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($integrationMerchantKey, 'client_username')->andReturn('');
    $this->redisService->shouldReceive('hGet')->once()->with($integrationMerchantKey, 'client_password')->andReturn('');

    $this->redisService->shouldReceive('hMSet')->andReturn(true);
    $this->redisService->shouldReceive('expire')->andReturn(true);
    $this->redisService->shouldReceive('pipelineCommands')->andReturn([]);
    $this->redisService->shouldReceive('get')->andReturn('token');
    $this->redisService->shouldReceive('set')->andReturn(true);

    RequestMockHelper::createExternalMock(200, ['data' => [
        'expires_in' => 3600,
    ]]);

    $this->refreshTokenHandler->handle(1);
});

test('deve não atualizar o token se o merchant estiver na fila de credenciais invalidas', function() {
    $merchantId = fake()->uuid();
    $this->redisService->shouldReceive('sMembers')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH)->andReturn([$merchantId]);
    $this->redisService->shouldReceive('sIsMember')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS, $merchantId)->andReturn(true);

    $this->redisService->shouldReceive('pipelineCommands')->andReturn([]);

    $this->refreshTokenHandler->handle(1);
});

test('deve não atualizar o token se o merchant não achar a enterprise id', function() {
    $merchantId = fake()->uuid();
    $this->redisService->shouldReceive('sMembers')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH)->andReturn([$merchantId]);
    $this->redisService->shouldReceive('sIsMember')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS, $merchantId)->andReturn(false);
    $merchantCredentialsKey = str_replace(['{provider}', '{merchantId}'], [Provider::AIQFOME, $merchantId], CacheKeys::PROVIDER_MERCHANTS_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($merchantCredentialsKey, 'enterpriseId')->andReturn('');

    $this->redisService->shouldReceive('pipelineCommands')->andReturn([]);

    $this->refreshTokenHandler->handle(1);
});

test('deve não atualizar o token se o merchant não achar o enterprise integration id', function() {
    $merchantId = fake()->uuid();
    $this->redisService->shouldReceive('sMembers')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH)->andReturn([$merchantId]);
    $this->redisService->shouldReceive('sIsMember')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS, $merchantId)->andReturn(false);
    $merchantCredentialsKey = str_replace(['{provider}', '{merchantId}'], [Provider::AIQFOME, $merchantId], CacheKeys::PROVIDER_MERCHANTS_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($merchantCredentialsKey, 'enterpriseId')->andReturn(1);
    $this->redisService->shouldReceive('hGet')->once()->with($merchantCredentialsKey, 'enterpriseIntegrationId')->andReturn('');

    $this->redisService->shouldReceive('pipelineCommands')->andReturn([]);

    $this->refreshTokenHandler->handle(1);
});

test('deve ser capaz de atualizar a partir do authenticate token', function() {
    $this->custom
        ->method('getParams')
        ->willReturn([
            'url' => fake()->url(),
            'credentials' => [
                'aiq-client-authorization' => fake()->name(),
                'aiq-user-agent' => fake()->name(),
                'client_secret' => fake()->sha256(),
                'client_id' => fake()->uuid(),
            ],
        ]);

    $merchantId = fake()->uuid();
    $this->redisService->shouldReceive('sMembers')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH)->andReturn([$merchantId]);
    $this->redisService->shouldReceive('sIsMember')->once()->with(RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS, $merchantId)->andReturn(false);
    $merchantCredentialsKey = str_replace(['{provider}', '{merchantId}'], [Provider::AIQFOME, $merchantId], CacheKeys::PROVIDER_MERCHANTS_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($merchantCredentialsKey, 'enterpriseId')->andReturn(1);
    $this->redisService->shouldReceive('hGet')->once()->with($merchantCredentialsKey, 'enterpriseIntegrationId')->andReturn(1);

    $integrationMerchantKey = str_replace('{integrationId}', 1, CacheKeys::ENTERPRISE_INTEGRATION_KEY);
    $this->redisService->shouldReceive('hGet')->once()->with($integrationMerchantKey, 'client_username')->andReturn('username');
    $this->redisService->shouldReceive('hGet')->once()->with($integrationMerchantKey, 'client_password')->andReturn('password');

    $this->redisService->shouldReceive('hMSet')->andReturn(true);
    $this->redisService->shouldReceive('expire')->andReturn(true);
    $this->redisService->shouldReceive('pipelineCommands')->andReturn([]);
    $this->redisService->shouldReceive('get')->andReturn('token');
    $this->redisService->shouldReceive('set')->andReturn(true);

    RequestMockHelper::createExternalMock(200, ['data' => [
        'expires_in' => 3600,
    ]]);

    $this->refreshTokenHandler->handle(1);
});

