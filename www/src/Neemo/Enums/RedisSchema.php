<?php

namespace src\Neemo\Enums;

abstract class RedisSchema
{
    const KEY_CRENDENTIAL_MERCHANT = 'it:neemo:cdt:{merchant_id}';
    const KEY_ORDER_DETAILS = 'it:neemo:orders:dl:{order_id}';

    const KEY_LIST_PENDING_ORDER_EVENTS = 'it:neemo:orders:ped';
    const KEY_LIST_MONITORING_ORDER_STATUS = 'it:neemo:orders:mont';
    const KEY_RATE_LIMIT_ORDERS = 'it:neemo:rt:orders:{merchant_id}';

    const KEY_MONITOR_PROCESS_PENDING_ORDERS = 'it:neemo:mt:po';
    const KEY_MONITOR_PROCESS_MONITORING_ORDERS = 'it:neemo:mt:mnt';
}