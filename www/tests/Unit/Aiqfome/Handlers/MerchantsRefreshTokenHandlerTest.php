<?php

use src\Aiqfome\Handlers\MerchantsRefreshTokenHandler;

use function Pest\Faker\fake;

beforeEach(function () {
    /** @var RedisService */
    $this->redisService = Mockery::mock('src\geral\RedisService');
    $this->getAccessTokenHandler = new MerchantsRefreshTokenHandler($this->redisService);
});

test('deve retornar merchants para atualizar token', function() {
    $merchants = [
        fake()->uuid(),
        fake()->uuid(),
        fake()->uuid(),
    ];

    $this->redisService->shouldReceive('sMembers')->andReturn($merchants);

    $result = $this->getAccessTokenHandler->getMerchantsForRefresh();

    expect($result)->toBe($merchants);
});

test('deve lançar uma exception se não houver merchants para atualizar', function() {
    $this->redisService->shouldReceive('sMembers')->andReturn([]);

    $this->getAccessTokenHandler->getMerchantsForRefresh();
})->throws(UnderflowException::class);

test('deve retornar se o merchant existe na fila de atualização de token', function() {
    $merchantId = fake()->uuid();

    $this->redisService->shouldReceive('sIsMember')->andReturn(true);

    $result = $this->getAccessTokenHandler->checkMerchantInQueueForRefresh($merchantId);

    expect($result)->toBeTrue();
});

test('deve retornar se o merchant existe na lista de credenciais inválidas', function() {
    $merchantId = fake()->uuid();

    $this->redisService->shouldReceive('sIsMember')->andReturn(true);

    $result = $this->getAccessTokenHandler->checkMerchantInInvalidCredentials($merchantId);

    expect($result)->toBeTrue();
});

test('deve retornar merchants para desativar integração', function() {
    $merchants = [
        fake()->uuid(),
        fake()->uuid(),
        fake()->uuid(),
    ];

    $this->redisService->shouldReceive('lRange')->andReturn($merchants);

    $result = $this->getAccessTokenHandler->getMerchantsForDesactivateIntegration(3);

    expect($result)->toBe($merchants);
});

test('deve lançar uma exception se não houver merchants para desativar integração', function() {
    $this->redisService->shouldReceive('lRange')->andReturn([]);

    $this->getAccessTokenHandler->getMerchantsForDesactivateIntegration(3);
})->throws(UnderflowException::class);

test('deve remover merchants da lista de desativação de integração', function() {
    $this->redisService->shouldReceive('lTrim')->andReturn(1);

    expect($this->getAccessTokenHandler->removeMerchantsForDesactivateIntegration(3))->toBeTrue();
});

