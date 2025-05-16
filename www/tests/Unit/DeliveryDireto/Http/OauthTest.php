<?php

use src\DeliveryDireto\Http\Oauth;
use src\geral\RedisService;
use src\DeliveryDireto\Enums\RedisSchema;
use src\geral\Enums\RequestConstants;
use Tests\Helpers\MockHelpers\RequestMockHelper;
use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);
    $this->deliveryDiretoId = fake()->uuid();
    $this->username = fake()->userName();
    $this->password = fake()->password();
    $this->hashRedis = md5($this->username . $this->password . $this->deliveryDiretoId);
    $this->keyRedis = str_replace('{hash}', $this->hashRedis, RedisSchema::KEY_OAUTH_ACCESS_TOKEN);
});

test('obtém cabeçalhos', function () {
    $accessToken = fake()->sha256();

    $this->redisClient
        ->expects($this->once())
        ->method('get')
        ->with($this->keyRedis)
        ->willReturn($accessToken);

    $oauth = new Oauth($this->redisClient, $this->deliveryDiretoId, $this->username, $this->password);

    $headers = $oauth->getHeadersRequests();

    expect($headers)->toBeArray();
    expect($headers[0])->toBe('Content-Type: application/json');
    expect($headers[1])->toBe('X-DeliveryDireto-ID: ' . $this->deliveryDiretoId);
    expect($headers[3])->toBe('Authorization: Bearer ' . $accessToken);
});

test('gera novo token', function () {
    $this->redisClient
        ->expects($this->once())
        ->method('get')
        ->with($this->keyRedis)
        ->willReturn(null);

    $this->redisClient
        ->expects($this->once())
        ->method('set')
        ->with(
            $this->keyRedis,
            $this->callback(fn($value) => is_string($value)),
            $this->callback(fn($options) => isset($options['EX']) && is_int($options['EX']))
        );

    $accessToken = fake()->sha256();
    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
        'access_token' => $accessToken,
        'expires_in' => 3600,
    ]);

    $oauth = new Oauth($this->redisClient, $this->deliveryDiretoId, $this->username, $this->password);

    $headers = $oauth->getHeadersRequests();
    expect($headers)->toBeArray();
    expect($headers[0])->toBe('Content-Type: application/json');
    expect($headers[1])->toBe('X-DeliveryDireto-ID: ' . $this->deliveryDiretoId);
    expect($headers[3])->toBe('Authorization: Bearer ' . $accessToken);
});

test('exceção resposta não HTTP_OK', function () {
    $this->redisClient
        ->expects($this->once())
        ->method('get')
        ->with($this->keyRedis)
        ->willReturn(null);

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_FORBIDDEN, []);

    $this->expectException(UnexpectedValueException::class);
    $this->expectExceptionMessage("[Auth] Unexpected HTTP response code: 403.");

    new Oauth($this->redisClient, $this->deliveryDiretoId, $this->username, $this->password);
});

test('exceção resposta sem access_token', function () {
    $this->redisClient
        ->expects($this->once())
        ->method('get')
        ->with($this->keyRedis)
        ->willReturn(null);

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, []);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("[Auth] Access token or expiration time is missing in the response.");

    new Oauth($this->redisClient, $this->deliveryDiretoId, $this->username, $this->password);
});