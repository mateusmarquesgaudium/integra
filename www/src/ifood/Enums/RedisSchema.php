<?php

namespace src\ifood\Enums;

abstract class RedisSchema
{
    const KEY_DISABLE_POLLING = 'it:ifood:dis:polling';
    const KEY_ENABLE_WEBHOOK = 'it:ifood:en:webhook';
    const KEY_OAUTH_ACCESS_TOKEN = 'it:ifood:oauth:token';
    const KEY_OAUTH_WEBHOOK_ACCESS_TOKEN = 'it:ifood:oauth:wbhtoken';
    const KEY_RATE_LIMIT_ORDERS = 'it:ifood:rt:orders';
    const KEY_RATE_LIMIT_ORDERS_EVENTS = 'it:ifood:rt:orders:ev:{event_type}';
    const KEY_EVENT_ORDER_APPROVED = 'it:ifood:orders:app';
    const KEY_ORDER_DETAILS = 'it:ifood:orders:dl:{order_id}';
    const KEY_LIST_APPROVED_ORDER_EVENTS = 'it:ifood:orders:apd';
    const KEY_LIST_PENDING_ORDER_EVENTS = 'it:ifood:orders:pend:{order_id}';
    const KEY_LIST_ORDERS_EVENTS = 'it:ifood:orders:events';
    const KEY_RETRY_ORDER_EVENTS = 'it:ifood:orders:retry:{order_id}:{event_type}';
    const KEY_MONITOR_PROCESS_PENDING_ORDERS = 'it:ifood:mt:po';
    const KEY_MONITOR_PROCESS_ORDER_EVENTS = 'it:ifood:mt:oe';
    const KEY_LAST_EVENT_PROCESSED = 'it:ifood:le:{order_id}';
    const KEY_ENABLE_LOGS = 'it:ifood:en:logs';
    const KEY_MAX_PROCESS_ORDER_EVENTS = 'it:ifood:mx:po';
    const KEY_ORDER_CODE_INVALID = 'it:if2:code:ivd:{order_id}';
}