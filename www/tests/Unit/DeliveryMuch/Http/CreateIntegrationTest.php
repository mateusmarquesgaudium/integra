<?php

use src\DeliveryMuch\Http\CreateIntegration;
use src\geral\Enums\RequestConstants;
use src\geral\Request;
use Tests\Helpers\MockHelpers\RequestMockHelper;

use function Pest\Faker\fake;

test('integração cadastrada', function () {
    $webhookId = fake()->md5();

    RequestMockHelper::createExternalMock(RequestConstants::HTTP_OK, [['_id' => $webhookId]]);
    $this->request = new Request(fake()->url());
    $this->createIntegration = new CreateIntegration($this->request, fake()->email(), fake()->password(), fake()->uuid(), fake()->md5());

    $result = $this->createIntegration->createIntegration();
    expect($result)->toBe($webhookId);
});

test('integração não cadastrada', function () {
    RequestMockHelper::createExternalMock(RequestConstants::HTTP_INTERNAL_SERVER_ERROR, []);
    $this->request = new Request(fake()->url());
    $this->createIntegration = new CreateIntegration($this->request, fake()->email(), fake()->password(), fake()->uuid(), fake()->md5());

    $this->expectException(\Exception::class);

    $this->createIntegration->createIntegration();
});