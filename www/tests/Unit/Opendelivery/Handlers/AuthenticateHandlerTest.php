<?php

use src\Delivery\Enums\Provider;
use src\geral\Custom;
use src\geral\RedisService;
use src\geral\CustomException;
use src\geral\JWTHandler;
use src\Opendelivery\Handlers\AuthenticateHandler;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);
    $this->custom = new Custom();
    $this->provider = Provider::SAIPOS;
});

afterEach(function () {
    Mockery::close();
});

test('authorization vazio', function () {
    $this->expectException(CustomException::class);
    $this->expectExceptionMessage('Invalid credentials');

    $authenticateHandler = new AuthenticateHandler('', $this->custom, $this->redisClient);
    $authenticateHandler->validarToken();
});

test('authorization sem o Bearer', function () {
    $this->expectException(CustomException::class);
    $this->expectExceptionMessage('Invalid Authorization header format');

    $authenticateHandler = new AuthenticateHandler('token_qualquer', $this->custom, $this->redisClient);
    $authenticateHandler->validarToken();
});

test('token inválido', function () {
    $this->expectException(CustomException::class);
    $this->expectExceptionMessage('User no authenticated');

    $this->redisClient->expects($this->once())->method('get')->willReturn(null);

    $authenticateHandler = new AuthenticateHandler('Bearer token', $this->custom, $this->redisClient);
    $authenticateHandler->validarToken();
});

test('token válido', function () {
    $jwtHandler = new JWTHandler($this->custom->getOpenDelivery()['secret']);
    $token = 'Bearer ' . $jwtHandler->encode(['provider' => $this->provider]);

    $this->redisClient->expects($this->once())->method('get')->willReturn(1);

    $authenticateHandler = new AuthenticateHandler($token, $this->custom, $this->redisClient);
    $authenticateHandler->validarToken();

    expect($authenticateHandler->getProvider())->toBe($this->provider);
});