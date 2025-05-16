<?php

use src\Opendelivery\Entities\OrderEventsStateMachine;
use src\Opendelivery\Enums\DeliveryStatus;

dataset('transition', [
    [DeliveryStatus::PENDING, DeliveryStatus::ACCEPTED, true],
    [DeliveryStatus::PENDING, DeliveryStatus::REJECTED, true],
    [DeliveryStatus::ACCEPTED, DeliveryStatus::PICKUP_ONGOING, true],
    [DeliveryStatus::UNATTENDED, DeliveryStatus::ACCEPTED, true],
    [DeliveryStatus::UNATTENDED, DeliveryStatus::REJECTED, true],
    [DeliveryStatus::PICKUP_ONGOING, DeliveryStatus::ARRIVED_AT_MERCHANT, true],
    [DeliveryStatus::ARRIVED_AT_MERCHANT, DeliveryStatus::ORDER_PICKED, true],
    [DeliveryStatus::ORDER_PICKED, DeliveryStatus::DELIVERY_ONGOING, true],
    [DeliveryStatus::DELIVERY_ONGOING, DeliveryStatus::ARRIVED_AT_CUSTOMER, true],
    [DeliveryStatus::ARRIVED_AT_CUSTOMER, DeliveryStatus::ORDER_DELIVERED, true],
    [DeliveryStatus::ORDER_DELIVERED, DeliveryStatus::RETURNING_TO_MERCHANT, true],
    [DeliveryStatus::ORDER_DELIVERED, DeliveryStatus::DELIVERY_FINISHED, true],
    [DeliveryStatus::RETURNING_TO_MERCHANT, DeliveryStatus::RETURNED_TO_MERCHANT, true],
    [DeliveryStatus::RETURNED_TO_MERCHANT, DeliveryStatus::DELIVERY_FINISHED, true],
    [DeliveryStatus::DELIVERY_FINISHED, DeliveryStatus::ACCEPTED, false],
    [DeliveryStatus::PENDING, DeliveryStatus::ORDER_DELIVERED, false],
    [DeliveryStatus::ACCEPTED, DeliveryStatus::CANCELLED, true],
    [DeliveryStatus::DELIVERY_ONGOING, DeliveryStatus::CANCELLED, true],
    [DeliveryStatus::CANCELLED, DeliveryStatus::DELIVERY_FINISHED, false],
    [DeliveryStatus::ORDER_DELIVERED, DeliveryStatus::PENDING, false],
    [DeliveryStatus::ORDER_PICKED, DeliveryStatus::PENDING, false],
    [DeliveryStatus::DELIVERY_ONGOING, DeliveryStatus::PENDING, false],
]);

test('transições entre estados corretamente', function (string  $initialState, string $newState, bool $expectedResult) {
    $orderEventStateMachine = new OrderEventsStateMachine($initialState);
    expect($orderEventStateMachine->transition($newState))->toBe($expectedResult);
})->with('transition');

dataset('isNewStateEarlierThanCurrentState', [
    [DeliveryStatus::PENDING, DeliveryStatus::ACCEPTED, false],
    [DeliveryStatus::DELIVERY_FINISHED, DeliveryStatus::ORDER_DELIVERED, true],
    [DeliveryStatus::ORDER_PICKED, DeliveryStatus::ARRIVED_AT_MERCHANT, true],
    [DeliveryStatus::ARRIVED_AT_CUSTOMER, DeliveryStatus::ORDER_DELIVERED, false],
    [DeliveryStatus::ACCEPTED, DeliveryStatus::PENDING, true],
    [DeliveryStatus::DELIVERY_ONGOING, DeliveryStatus::ORDER_PICKED, true],
    [DeliveryStatus::ARRIVED_AT_CUSTOMER, DeliveryStatus::DELIVERY_ONGOING, true],
    [DeliveryStatus::DELIVERY_FINISHED, DeliveryStatus::RETURNED_TO_MERCHANT, true],
    [DeliveryStatus::DELIVERY_ONGOING, DeliveryStatus::CANCELLED, false],
    [DeliveryStatus::CANCELLED, DeliveryStatus::ACCEPTED, false],
    [DeliveryStatus::ACCEPTED, DeliveryStatus::REJECTED, false],
]);

test('verifica se o novo estado é anterior ao estado atual', function (string $initialState, string  $newState, bool $expectedResult) {
    $orderEventStateMachine = new OrderEventsStateMachine($initialState);
    expect($orderEventStateMachine->isNewStateEarlierThanCurrentState($newState))->toBe($expectedResult);
})->with('isNewStateEarlierThanCurrentState');