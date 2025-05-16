<?php

namespace src\AnotaAi\Enums;

abstract class OrderStatus
{
    const APPOINTMENT_ACCEPTED = -2;
    const IN_REVIEW = 0;
    const IN_PRODUCTION = 1;
    const READY = 2;
    const IN_CANCELLATION_REQUEST = 6;
}