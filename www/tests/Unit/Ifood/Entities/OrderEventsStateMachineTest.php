<?php

use src\ifood\Entities\OrderEventsStateMachine;
use src\ifood\Enums\DeliveryStatus;

// Dataset para transições válidas e inválidas
dataset('transition', [
    [DeliveryStatus::ASSIGN_DRIVER, DeliveryStatus::GOING_TO_ORIGIN, true],
    [DeliveryStatus::GOING_TO_ORIGIN, DeliveryStatus::ARRIVED_AT_ORIGIN, true],
    [DeliveryStatus::ARRIVED_AT_ORIGIN, DeliveryStatus::DISPATCHED, true],
    [DeliveryStatus::DISPATCHED, DeliveryStatus::ARRIVED_AT_DESTINATION, true],
    [DeliveryStatus::GOING_TO_ORIGIN, DeliveryStatus::DISPATCHED, false],
    [DeliveryStatus::ARRIVED_AT_ORIGIN, DeliveryStatus::ARRIVED_AT_DESTINATION, false],
    [DeliveryStatus::DISPATCHED, DeliveryStatus::GOING_TO_ORIGIN, false],
    [DeliveryStatus::ARRIVED_AT_DESTINATION, DeliveryStatus::DISPATCHED, false],
    [DeliveryStatus::ASSIGN_DRIVER, DeliveryStatus::ARRIVED_AT_ORIGIN, false],
    [DeliveryStatus::GOING_TO_ORIGIN, DeliveryStatus::GOING_TO_ORIGIN, false],
    [DeliveryStatus::ARRIVED_AT_ORIGIN, DeliveryStatus::ARRIVED_AT_ORIGIN, false],
    [DeliveryStatus::DISPATCHED, DeliveryStatus::DISPATCHED, false],
    [DeliveryStatus::ARRIVED_AT_DESTINATION, DeliveryStatus::ARRIVED_AT_DESTINATION, false],
    ['', DeliveryStatus::ASSIGN_DRIVER, true],
]);

test('transiciona entre estados corretamente', function (string $initialState, string $newState, bool $expectedResult) {
    $orderEventsStateMachine = new OrderEventsStateMachine($initialState);
    expect($orderEventsStateMachine->transition($newState))->toBe($expectedResult);
})->with('transition');
