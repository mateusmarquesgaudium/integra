<?php

namespace src\Delivery\Enums;

abstract class OrderStatus
{
    const APPROVED = 'APPROVED';
    const IN_TRANSIT = 'IN_TRANSIT';
    const DONE = 'DONE';
    const HIDDEN = 'HIDDEN';
}