<?php

use src\Aiqfome\Http\DeleteIntegration;
use src\Aiqfome\Handlers\CheckMerchantInProviderHandler;
use src\Aiqfome\Handlers\GetAccessTokenHandler;
use src\geral\Custom;
use src\geral\RedisService;
use src\geral\Request;
use src\geral\RequestMulti;
use src\Delivery\Enums\Provider;
use Tests\Helpers\MockHelpers\RequestMockHelper;

use function Pest\Faker\fake;

beforeEach(function () {
    $this->customMock = Mockery::mock(Custom::class);
    $this->redisServiceMock = Mockery::mock(RedisService::class);
    /** @var CheckMerchantInProviderHandler */
    $this->checkMerchantInProviderHandlerMock = Mockery::mock(CheckMerchantInProviderHandler::class);

    $this->customMock->shouldReceive('getParams')
        ->with('aiqfome')
        ->andReturn([
            'url' => fake()->url(),
            'credentials' => [
                'aiq-client-authorization' => 'mocked-client-auth',
                'aiq-user-agent' => 'mocked-user-agent',
            ],
        ]);
});

test('deve lançar uma exception se não encontrar o merchant', function () {
    $this->checkMerchantInProviderHandlerMock->shouldReceive('execute')
        ->andReturn(false);

    $deleteIntegration = new DeleteIntegration($this->customMock, $this->redisServiceMock, $this->checkMerchantInProviderHandlerMock);
    $deleteIntegration->execute(fake()->uuid(), [1, 2, 3]);
})->throws(\Exception::class);

test('deve executar a exclusão dos webhooks com sucesso', function() {
    $this->checkMerchantInProviderHandlerMock->shouldReceive('execute')
        ->andReturn(true);

    $this->getAccessTokenHandlerMock = Mockery::mock(GetAccessTokenHandler::class);
    $this->getAccessTokenHandlerMock->shouldReceive('execute')
        ->with('mocked-merchant-id', Provider::AIQFOME)
        ->andReturn('mocked-access-token');

    $this->requestMultiMock = Mockery::mock('overload:RequestMulti');
    $this->requestMultiMock->shouldReceive('execute')->once()->andReturn(true);

    $this->redisServiceMock->shouldReceive('hGet')->andReturn('mocked-access-token');

    RequestMockHelper::createExternalMock(200, []);

    $deleteIntegration = new DeleteIntegration($this->customMock, $this->redisServiceMock, $this->checkMerchantInProviderHandlerMock);
    $deleteIntegration->execute('mocked-merchant-id', [1, 2, 3]);
});
