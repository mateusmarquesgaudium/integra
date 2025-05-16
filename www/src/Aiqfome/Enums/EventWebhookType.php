<?php

namespace src\Aiqfome\Enums;

abstract class EventWebhookType
{
    const CANCEL_ORDER = 'CANCEL-ORDER';
    const READ_ORDER = 'READ-ORDER';
    const READY_ORDER = 'READY-ORDER';
}