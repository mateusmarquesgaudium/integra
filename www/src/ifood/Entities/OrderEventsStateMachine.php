<?php
namespace src\ifood\Entities;

use src\ifood\Enums\DeliveryStatus;

class OrderEventsStateMachine
{
    private $currentState;

    public function __construct($initialState)
    {
        $this->currentState = $initialState;
    }

    public function transition($newState): bool
    {
        $validTransitions = [
            DeliveryStatus::ASSIGN_DRIVER => [DeliveryStatus::GOING_TO_ORIGIN],
            DeliveryStatus::GOING_TO_ORIGIN => [DeliveryStatus::ARRIVED_AT_ORIGIN],
            DeliveryStatus::ARRIVED_AT_ORIGIN => [DeliveryStatus::DISPATCHED],
            DeliveryStatus::DISPATCHED => [DeliveryStatus::ARRIVED_AT_DESTINATION],
            DeliveryStatus::ARRIVED_AT_DESTINATION => []
        ];

        if ($newState == DeliveryStatus::ASSIGN_DRIVER) {
            $this->currentState = $newState;
            return true;
        }

        if (isset($validTransitions[$this->currentState]) && in_array($newState, $validTransitions[$this->currentState])) {
            $this->currentState = $newState;
            return true;
        }

        return false;
    }
}