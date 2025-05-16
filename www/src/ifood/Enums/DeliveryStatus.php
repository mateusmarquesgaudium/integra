<?php
namespace src\ifood\Enums;
abstract class DeliveryStatus
{
    const ASSIGN_DRIVER = 'ASSIGN_DRIVER';
    const GOING_TO_ORIGIN = 'GOING_TO_ORIGIN';
    const ARRIVED_AT_ORIGIN = 'ARRIVED_AT_ORIGIN';
    const DISPATCHED = 'DISPATCHED';
    const ARRIVED_AT_DESTINATION = 'ARRIVED_AT_DESTINATION';
}