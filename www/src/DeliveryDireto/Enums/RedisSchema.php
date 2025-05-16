<?php

namespace src\DeliveryDireto\Enums;

abstract class RedisSchema
{
    const KEY_OAUTH_ACCESS_TOKEN = 'it:dld:oauth:act:{hash}';
    const KEY_CRENDENTIAL_MERCHANT = 'it:dld:cdt:{merchant_id}';
    const KEY_RATE_LIMIT_ORDERS = 'it:dld:rt:orders:{merchant_id}';
    const KEY_RATE_LIMIT_ORDER_STATUS = 'it:dld:rt:ost:{merchant_id}';

    const KEY_EVENT_ORDER_APPROVED = 'it:dld:orders:app';
    const KEY_ORDER_DETAILS = 'it:dld:orders:dl:{order_id}';
    const KEY_LIST_APPROVED_ORDER_EVENTS = 'it:dld:orders:apd';
    const KEY_LIST_PENDING_ORDER_EVENTS = 'it:dld:orders:pend:{order_id}';

    const KEY_MONITOR_PROCESS_PENDING_ORDERS = 'it:dld:mt:po';
}