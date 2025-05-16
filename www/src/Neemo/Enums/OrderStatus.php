<?php

namespace src\Neemo\Enums;

abstract class OrderStatus
{
    const NEW_ORDER = 0;
    const CONFIRMED = 1;
    const DELIVERED = 2;
    const SHIPPED = 4;
    const AWAITING_ONLINE_PAYMENT_APPROVAL = 8;
}