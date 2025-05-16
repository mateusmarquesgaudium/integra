<?php

use src\Aiqfome\Enums\RedisSchema;
use src\Aiqfome\Http\AuthenticateToken;
use src\Aiqfome\Http\RefreshToken;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use Tests\Helpers\MockHelpers\RequestMockHelper;

use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisService = $this->createMock(RedisService::class);
    $this->custom = $this->createMock(Custom::class);
});

test('deve ser capaz de autenticar a integração', function () {
    $this->redisService->method('exists')->willReturn(false);
    $this->redisService->expects($this->once())->method('hMSet')->willReturn(true);
    $this->redisService->expects($this->once())->method('expire')->willReturn(true);

    $url = fake()->url();
    $this->custom
        ->method('getParams')
        ->willReturn([
            'url' => $url,
            'credentials' => [
                'aiq-client-authorization' => fake()->name(),
                'aiq-user-agent' => fake()->name(),
                'client_secret' => fake()->sha256(),
                'client_id' => fake()->uuid(),
                'user_email' => fake()->email(),
                'password' => fake()->password(),
            ],
        ]);

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, ['data' => [
        'expires_in' => 3600,
    ]]);
    $this->request = new Request($url);

    $this->authenticate = new AuthenticateToken($this->request, $this->custom, $this->redisService);

    $this->authenticate->handle(fake()->name(), fake()->sha256(), '1');
});

test('deve lançar uma exception quando não for possível autenticar', function() {
    $this->redisService->method('exists')->willReturn(false);
    $this->redisService->method('hMSet')->willReturn(true);
    $this->redisService->method('set')->willReturn(true);
    $this->redisService->method('expire')->willReturn(true);

    $url = fake()->url();
    $this->custom
        ->method('getParams')
        ->willReturn([
            'url' => $url,
            'credentials' => [
                'aiq-client-authorization' => fake()->name(),
                'aiq-user-agent' => fake()->name(),
                'client_secret' => fake()->sha256(),
                'client_id' => fake()->uuid(),
            ],
        ]);

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_FORBIDDEN, ['data' => [
        'expires_in' => 3600,
    ]]);
    $this->request = new Request($url);

    $this->authenticate = new AuthenticateToken($this->request, $this->custom, $this->redisService);

    $this->authenticate->handle(fake()->name(), fake()->sha256(), '1');
})->throws(\Exception::class);
