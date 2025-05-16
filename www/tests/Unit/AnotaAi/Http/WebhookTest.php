<?php

use src\geral\RedisService;
use src\AnotaAi\Http\Oauth;
use src\AnotaAi\Http\Webhook;
use src\geral\Enums\RequestConstants;
use Tests\Helpers\MockHelpers\RequestMockHelper;

use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);
    $this->merchantToken = fake()->sha256();
    $this->merchantUrl = fake()->url();

    $oauth = $this->createMock(Oauth::class);
    $oauth->method('getHeadersRequests')->willReturn([
        'Content-Type: application/json',
        'Authorization: Bearer ' . $this->merchantToken,
    ]);
    $this->webhook = new Webhook($oauth);
});

test('webhook criado', function () {
    $token = fake()->sha256();

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_CREATED, [
        'token' => $token,
    ]);

    $result = $this->webhook->createWebhook($this->merchantToken, $this->merchantUrl);
    expect($result)->toBe($token);
});

test('webhook não criado', function () {
    RequestMockHelper::createExternalMock(RequestConstants::HTTP_FORBIDDEN, []);

    $result = $this->webhook->createWebhook($this->merchantToken, $this->merchantUrl);
    expect($result)->toBeFalse();
});

test('webhook criado, mas sem token', function () {
    RequestMockHelper::createExternalMock(RequestConstants::HTTP_CREATED, []);

    $result = $this->webhook->createWebhook($this->merchantToken, $this->merchantUrl);
    expect($result)->toBeFalse();
});