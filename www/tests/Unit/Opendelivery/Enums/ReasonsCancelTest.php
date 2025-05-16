<?php

use src\Opendelivery\Enums\ReasonsCancel;

test('verifica se a lista de motivos de cancelamento contém os motivos essenciais', function () {
    $allReasons = ReasonsCancel::getAllReasons();

    expect($allReasons)->toHaveKeys([
        'CONSUMER_CANCELLATION_REQUESTED',
        'NO_SHOW',
        'PROBLEM_AT_MERCHANT'
    ]);
});

test('verifica se o método isValidReason funciona para motivos válidos e inválidos', function () {
    $validReason = ReasonsCancel::isValidReason('CONSUMER_CANCELLATION_REQUESTED');
    expect($validReason)->toBeTrue();

    $invalidReason = ReasonsCancel::isValidReason('INVALID_REASON');
    expect($invalidReason)->toBeFalse();
});
