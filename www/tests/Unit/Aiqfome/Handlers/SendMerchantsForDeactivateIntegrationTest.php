<?php

use src\Aiqfome\Handlers\SendMerchantsForDeactivateIntegration;
use src\geral\Request;
use Tests\Helpers\MockHelpers\RequestMockHelper;

use function Pest\Faker\fake;

beforeEach(function () {
    $this->custom = Mockery::mock('src\geral\Custom');
    $this->merchantsId = [1, 2, 3];
});

test('deve retornar os merchants para desativar integração', function() {
    $this->custom->shouldReceive('getParams')->andReturn([
        'aiqfome' => [
            'maxMerchantsInvalid' => 3,
        ],
    ]);

    RequestMockHelper::createExternalMock(200, ['status' => 'success']);
    $this->request = new Request(fake()->url());
    $this->sendMerchantsForDeactivateIntegration = new SendMerchantsForDeactivateIntegration($this->custom, $this->request, $this->merchantsId);

    $result = $this->sendMerchantsForDeactivateIntegration->handle();

    expect(!empty($result))->toBeTrue();
});

test('deve lançar uma exception se não houver content para desativar integração', function() {
    $this->custom->shouldReceive('getParams')->andReturn([
        'aiqfome' => [
            'maxMerchantsInvalid' => 3,
        ],
    ]);

    RequestMockHelper::createExternalMock(200, []);
    $this->request = new Request(fake()->url());
    $this->sendMerchantsForDeactivateIntegration = new SendMerchantsForDeactivateIntegration($this->custom, $this->request, $this->merchantsId);

    $this->sendMerchantsForDeactivateIntegration->handle();
})->throws(\Exception::class);