<?php

use src\DeliveryDireto\Enums\WebhookType;
use src\DeliveryDireto\Http\Oauth;
use src\DeliveryDireto\Http\Webhook;
use src\geral\Enums\RequestConstants;
use Tests\Helpers\MockHelpers\RequestMockHelper;

use function Pest\Faker\fake;

beforeEach(function () {
    $this->oauth = $this->createMock(Oauth::class);
    $this->oauth->method('getHeadersRequests')->willReturn([]);
    $this->webhook = new Webhook($this->oauth);
});

test('createWebhook', function (int $httpCode, bool $expected) {
    RequestMockHelper::createExternalMock($httpCode, []);

    $result = $this->webhook->createWebhook(WebhookType::ORDER_STATUS_CHANGED, fake()->url());
    expect($result)->toBe($expected);
})->with([
    'requisição válida HTTP_OK' => [RequestConstants::HTTP_OK, true],
    'requisição inválida HTTP_FORBIDDEN' => [RequestConstants::HTTP_FORBIDDEN, false],
]);

test('deleteWebhook', function (int $httpCode, bool $expected) {
    RequestMockHelper::createExternalMock($httpCode, []);

    $result = $this->webhook->deleteWebhook(WebhookType::ORDER_STATUS_CHANGED);
    expect($result)->toBe($expected);
})->with([
    'requisição válida HTTP_NO_CONTENT' => [RequestConstants::HTTP_NO_CONTENT, true],
    'requisição válida HTTP_NOT_FOUND' => [RequestConstants::HTTP_NOT_FOUND, true],
    'requisição inválida HTTP_FORBIDDEN' => [RequestConstants::HTTP_FORBIDDEN, false],
]);