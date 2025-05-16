<?php

use src\ifood\Http\Oauth;
use src\geral\RedisService;
use src\ifood\Enums\RedisSchema;
use src\geral\Enums\RequestConstants;
use src\ifood\Enums\OauthMode;
use Tests\Helpers\MockHelpers\RequestMockHelper;
use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);
});

afterEach(function () {
    Mockery::close();
});

test('obtém cabeçalhos', function (string $oauthMode, string $keyRedis) {
    $accessToken = fake()->sha256();

    $this->redisClient
        ->expects($this->once())
        ->method('get')
        ->with($keyRedis)
        ->willReturn($accessToken);

    $oauth = new Oauth($this->redisClient, $oauthMode);

    $headers = $oauth->getHeadersRequests();

    expect($headers)->toBeArray();
    expect($headers[0])->toBe('Content-Type: application/json');
    expect($headers[1])->toBe('Authorization: Bearer ' . $accessToken);
})->with([
    [OauthMode::POLLING, RedisSchema::KEY_OAUTH_ACCESS_TOKEN],
    [OauthMode::WEBHOOK, RedisSchema::KEY_OAUTH_WEBHOOK_ACCESS_TOKEN],
]);

test('gera novo token', function (string $oauthMode, string $keyRedis) {
    $this->redisClient
        ->expects($this->once())
        ->method('get')
        ->with($keyRedis)
        ->willReturn(null);

    $this->redisClient
        ->expects($this->once())
        ->method('set')
        ->with(
            $keyRedis,
            $this->callback(fn($value) => is_string($value)),
            $this->callback(fn($options) => isset($options['EX']) && is_int($options['EX']))
        );

    $accessToken = fake()->sha256();
    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
        'accessToken' => $accessToken,
        'expiresIn' => 3600,
    ]);

    $oauth = new Oauth($this->redisClient, $oauthMode);

    $headers = $oauth->getHeadersRequests();
    expect($headers)->toBeArray();
    expect($headers[0])->toBe('Content-Type: application/json');
    expect($headers[1])->toBe('Authorization: Bearer ' . $accessToken);
})->with([
    [OauthMode::POLLING, RedisSchema::KEY_OAUTH_ACCESS_TOKEN],
    [OauthMode::WEBHOOK, RedisSchema::KEY_OAUTH_WEBHOOK_ACCESS_TOKEN],
]);

test('exceção resposta não HTTP_OK', function (string $oauthMode, string $keyRedis) {
    $this->redisClient
        ->expects($this->once())
        ->method('get')
        ->with($keyRedis)
        ->willReturn(null);

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_FORBIDDEN, []);

    $this->expectException(UnexpectedValueException::class);
    $this->expectExceptionMessage("[Auth] Unexpected HTTP response code: 403.");

    new Oauth($this->redisClient, $oauthMode);
})->with([
    [OauthMode::POLLING, RedisSchema::KEY_OAUTH_ACCESS_TOKEN],
    [OauthMode::WEBHOOK, RedisSchema::KEY_OAUTH_WEBHOOK_ACCESS_TOKEN],
]);

test('exceção resposta sem accessToken', function (string $oauthMode, string $keyRedis) {
    $this->redisClient
        ->expects($this->once())
        ->method('get')
        ->with($keyRedis)
        ->willReturn(null);

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
        'expiresIn' => 3600,
    ]);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("[Auth] Access token or expiration time is missing in the response.");

    new Oauth($this->redisClient, $oauthMode);
})->with([
    [OauthMode::POLLING, RedisSchema::KEY_OAUTH_ACCESS_TOKEN],
    [OauthMode::WEBHOOK, RedisSchema::KEY_OAUTH_WEBHOOK_ACCESS_TOKEN],
]);

test('exceção resposta sem expiresIn', function (string $oauthMode, string $keyRedis) {
    $this->redisClient
        ->expects($this->once())
        ->method('get')
        ->with($keyRedis)
        ->willReturn(null);

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
        'accessToken' => fake()->sha256(),
    ]);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("[Auth] Access token or expiration time is missing in the response.");

    new Oauth($this->redisClient, $oauthMode);
})->with([
    [OauthMode::POLLING, RedisSchema::KEY_OAUTH_ACCESS_TOKEN],
    [OauthMode::WEBHOOK, RedisSchema::KEY_OAUTH_WEBHOOK_ACCESS_TOKEN],
]);
