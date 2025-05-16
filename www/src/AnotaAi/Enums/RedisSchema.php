<?php

namespace src\AnotaAi\Enums;

abstract class RedisSchema
{
    const KEY_OAUTH_ACCESS_TOKEN = 'it:antai:oauth:act:{hash}';
    const KEY_CRENDENTIAL_MERCHANT = 'it:antai:cdt:{merchant_id}';
    const KEY_ORDER_DETAILS = 'it:antai:orders:dl:{order_id}';
    const KEY_LIST_PENDING_ORDER_EVENTS = 'it:antai:orders:pend';
    const KEY_LIST_MONITORING_ORDER_STATUS = 'it:antai:orders:mont';

    const KEY_ENABLE_LOGS = 'it:antai:en:logs';
    const KEY_RATE_LIMIT_ORDERS = 'it:antai:rt:orders:{merchant_id}';

    const KEY_MONITOR_PROCESS_PENDING_ORDERS = 'it:antai:mt:po';
    const KEY_MONITOR_PROCESS_MONITORING_ORDERS = 'it:antai:mt:mnt';
}