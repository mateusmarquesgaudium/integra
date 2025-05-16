<?php

use src\Delivery\Enums\Provider;
use src\geral\Custom;
use src\Opendelivery\Handlers\OauthHandler;
use src\geral\JWTHandler;
use src\geral\RedisService;
use src\geral\CustomException;
use src\geral\Enums\RequestConstants;
use src\Opendelivery\Enums\RedisSchema;
use src\Opendelivery\Enums\Variables;
use Tests\Helpers\MockHelpers\RequestMockHelper;

use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);

    $this->clientId = fake()->uuid();
    $this->enterpriseId = fake()->uuid();
    $this->provider = Provider::SAIPOS;

    $custom = new Custom();
    $this->customSecret = $custom->getOpenDelivery()['secret'];
    $this->jwtHandler = new JWTHandler($this->customSecret);

    $this->payload = ['empresa_id' => $this->enterpriseId, 'provider' => $this->provider];
    $this->clientSecret = $this->jwtHandler->encode($this->payload);

    $this->keyEnterpriseCredentials = str_replace(
        ['{provider}', '{empresa_id}', '{client_id}'],
        [$this->provider, $this->enterpriseId, $this->clientId],
        RedisSchema::KEY_CREDENTIALS_PROVIDER
    );
});

afterEach(function () {
    Mockery::close();
});

describe('authenticateUser', function () {
    test('clientId vazio', function () {
        $this->expectException(CustomException::class);
        $this->expectExceptionMessage(json_encode([
            'type' => Variables::TYPE_INVALID,
            'message' => 'Invalid client_id in Authorization header'
        ]));

        $oauthHandler = new OauthHandler($this->redisClient, '', $this->clientSecret);
        $oauthHandler->authenticateUser();
    });

    test('clientSecret vazia', function () {
        $this->expectException(CustomException::class);
        $this->expectExceptionMessage(json_encode([
            'type' => Variables::TYPE_INVALID,
            'message' => 'Invalid credentials for authorization'
        ]));

        $oauthHandler = new OauthHandler($this->redisClient, $this->clientId, '');
        $oauthHandler->authenticateUser();
    });

    test('clientSecret inválida', function () {
        $this->expectException(CustomException::class);
        $this->expectExceptionMessage(json_encode([
            'type' => Variables::TYPE_INVALID,
            'message' => 'Invalid credentials for authorization'
        ]));

        $oauthHandler = new OauthHandler($this->redisClient, $this->clientId, $this->clientSecret . 'invalid');
        $oauthHandler->authenticateUser();
    });

    test('empresa_id ausente no payload', function () {
        $this->expectException(CustomException::class);
        $this->expectExceptionMessage(json_encode([
            'type' => Variables::TYPE_INVALID,
            'message' => 'Invalid credentials for authorization'
        ]));

        $payload = ['provider' => Provider::SAIPOS];
        $clientSecret = $this->jwtHandler->encode($payload);

        $oauthHandler = new OauthHandler($this->redisClient, $this->clientId, $clientSecret);
        $oauthHandler->authenticateUser();
    });

    test('provider ausente no payload', function () {
        $this->expectException(CustomException::class);
        $this->expectExceptionMessage(json_encode([
            'type' => Variables::TYPE_INVALID,
            'message' => 'Invalid credentials for authorization'
        ]));

        $payload = ['empresa_id' => $this->enterpriseId];
        $clientSecret = $this->jwtHandler->encode($payload);

        $oauthHandler = new OauthHandler($this->redisClient, $this->clientId, $clientSecret);
        $oauthHandler->authenticateUser();
    });

    test('client_id inválido com o cache', function () {
        $this->expectException(CustomException::class);
        $this->expectExceptionMessage(json_encode([
            'type' => Variables::TYPE_INVALID,
            'message' => 'Invalid credentials for authorization'
        ]));

        $enterpriseCredentials = [
            'client_secret' => $this->clientSecret,
            'client_id' => 123,
        ];

        $this->redisClient->method('get')->with($this->keyEnterpriseCredentials)->willReturn(json_encode($enterpriseCredentials));

        $oauthHandler = new OauthHandler($this->redisClient, $this->clientId, $this->clientSecret);
        $oauthHandler->authenticateUser();
    });

    test('client_secret inválido com o cache', function () {
        $this->expectException(CustomException::class);
        $this->expectExceptionMessage(json_encode([
            'type' => Variables::TYPE_INVALID,
            'message' => 'Invalid credentials for authorization'
        ]));

        $enterpriseCredentials = [
            'client_secret' => 123,
            'client_id' => $this->clientId,
        ];

        $this->redisClient->method('get')->with($this->keyEnterpriseCredentials)->willReturn(json_encode($enterpriseCredentials));

        $oauthHandler = new OauthHandler($this->redisClient, $this->clientId, $this->clientSecret);
        $oauthHandler->authenticateUser();
    });

    test('companyCredentials vazio, retorno do monólito', function () {
        RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
            'status' => 'success',
            'client_secret' => $this->clientSecret,
            'client_id' => $this->clientId,
        ]);

        $newPayload = ['provider' => $this->provider];
        $accessToken = $this->jwtHandler->encode($newPayload);

        $this->redisClient->expects($this->exactly(2))->method('set')->willReturn(true);
        $this->redisClient->expects($this->exactly(2))->method('expire')->willReturn(true);
        $this->redisClient->expects($this->exactly(1))->method('get')->with($this->keyEnterpriseCredentials)->willReturn('');

        $oauthHandler = new OauthHandler($this->redisClient, $this->clientId, $this->clientSecret);
        $newAccessToken = $oauthHandler->authenticateUser();
        expect($newAccessToken)->toBe($accessToken);
    });
});

describe('checkCredentialsInMonolith', function () {
    test('resposta diferente de HTTP_OK', function () {
        RequestMockHelper::createExternalMock(RequestConstants::HTTP_FORBIDDEN, []);

        $this->expectException(CustomException::class);
        $this->expectExceptionMessage(json_encode([
            'type' => Variables::TYPE_INVALID,
            'message' => 'Invalid credentials for authorization'
        ]));
        $this->redisClient->expects($this->exactly(1))->method('get')->with($this->keyEnterpriseCredentials)->willReturn('');

        $oauthHandler = new OauthHandler($this->redisClient, $this->clientId, $this->clientSecret);
        $oauthHandler->authenticateUser();
    });

    test('resposta com status diferente de success', function () {
        RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [
            'status' => 'error',
        ]);

        $this->expectException(CustomException::class);
        $this->expectExceptionMessage(json_encode([
            'type' => Variables::TYPE_INVALID,
            'message' => 'Invalid credentials for authorization'
        ]));
        $this->redisClient->expects($this->exactly(1))->method('get')->with($this->keyEnterpriseCredentials)->willReturn('');

        $oauthHandler = new OauthHandler($this->redisClient, $this->clientId, $this->clientSecret);
        $oauthHandler->authenticateUser();
    });
});