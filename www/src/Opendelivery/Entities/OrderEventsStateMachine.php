<?php
namespace src\Opendelivery\Entities;

use src\Opendelivery\Enums\DeliveryStatus;

class OrderEventsStateMachine
{
    private string $currentState;

    public function __construct($initialState)
    {
        $this->currentState = $initialState;
    }

    public function transition($newState): bool
    {
        if (!$this->validateTransition($newState)) {
            return false;
        }

        $this->currentState = $newState;
        return true;
    }

    public function isNewStateEarlierThanCurrentState($newState): bool
    {
        $states = [
            DeliveryStatus::PENDING => 1,
            DeliveryStatus::ACCEPTED => 2,
            DeliveryStatus::UNATTENDED => 3,
            DeliveryStatus::PICKUP_ONGOING => 4,
            DeliveryStatus::ARRIVED_AT_MERCHANT => 5,
            DeliveryStatus::ORDER_PICKED => 6,
            DeliveryStatus::DELIVERY_ONGOING => 7,
            DeliveryStatus::ARRIVED_AT_CUSTOMER => 8,
            DeliveryStatus::ORDER_DELIVERED => 9,
            DeliveryStatus::RETURNING_TO_MERCHANT => 10,
            DeliveryStatus::RETURNED_TO_MERCHANT => 11,
            DeliveryStatus::DELIVERY_FINISHED => 12,
            DeliveryStatus::REJECTED => 13,
        ];

        if (!isset($states[$newState]) || !isset($states[$this->currentState])) {
            return false;
        }

        return $states[$newState] <= $states[$this->currentState];
    }

    private function validateTransition($newState): bool
    {
        $validTransitions = [
            DeliveryStatus::PENDING => [DeliveryStatus::ACCEPTED],
            DeliveryStatus::ACCEPTED => [DeliveryStatus::PICKUP_ONGOING],
            DeliveryStatus::UNATTENDED => [DeliveryStatus::ACCEPTED],
            DeliveryStatus::PICKUP_ONGOING => [DeliveryStatus::ARRIVED_AT_MERCHANT],
            DeliveryStatus::ARRIVED_AT_MERCHANT => [DeliveryStatus::ORDER_PICKED],
            DeliveryStatus::ORDER_PICKED => [DeliveryStatus::DELIVERY_ONGOING],
            DeliveryStatus::DELIVERY_ONGOING => [DeliveryStatus::ARRIVED_AT_CUSTOMER],
            DeliveryStatus::ARRIVED_AT_CUSTOMER => [DeliveryStatus::ORDER_DELIVERED],
            DeliveryStatus::ORDER_DELIVERED => [DeliveryStatus::DELIVERY_FINISHED, DeliveryStatus::RETURNING_TO_MERCHANT],
            DeliveryStatus::RETURNING_TO_MERCHANT => [DeliveryStatus::RETURNED_TO_MERCHANT],
            DeliveryStatus::RETURNED_TO_MERCHANT => [DeliveryStatus::DELIVERY_FINISHED],
            DeliveryStatus::DELIVERY_FINISHED => []
        ];

        if ($newState === DeliveryStatus::REJECTED) {
            return true;
        }

        // Estado cancelado pode ser alterado para qualquer estado, exceto para os estados PENDING, CANCELED e DELIVERY_FINISHED
        if ($newState === DeliveryStatus::CANCELLED && !in_array($this->currentState, [DeliveryStatus::PENDING, DeliveryStatus::CANCELLED, DeliveryStatus::DELIVERY_FINISHED])) {
            return true;
        }

        return in_array($newState, $validTransitions[$this->currentState] ?? []);
    }
}