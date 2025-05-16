<?php

use src\geral\Enums\RequestConstants;
use src\Neemo\Http\OrderList;
use Tests\Helpers\MockHelpers\RequestMockHelper;
use function Pest\Faker\fake;

beforeEach(function () {
    $this->tokenAccount = fake()->sha256();
    $this->orderList = new OrderList($this->tokenAccount);
});

test('requisição inválida', function () {
    RequestMockHelper::createExternalMock(RequestConstants::HTTP_FORBIDDEN, []);

    $result = $this->orderList->checkAccessOrderList();
    expect($result)->toBeFalse();
});

test('requisição válida', function ($httpCode) {
    RequestMockHelper::createExternalMock($httpCode, []);

    $result = $this->orderList->checkAccessOrderList();
    expect($result)->toBeTrue();
})->with([
    RequestConstants::HTTP_OK,
    RequestConstants::HTTP_ACCEPTED,
    RequestConstants::HTTP_CREATED
]);