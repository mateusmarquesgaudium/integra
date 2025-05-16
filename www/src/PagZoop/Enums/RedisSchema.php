<?php

namespace src\PagZoop\Enums;

abstract class RedisSchema
{
    const LIST_PAGZOOP_EVENTS_WEBHOOK = 'sub:wh:pay:evt:zoop:ev';
    const COUNTER_RETRIES_PAGZOOP = 'sub:wh:pay:evt:zoop:rt';
    const BLOCK_RETRIES_PAGZOOP = 'sub:wh:pay:evt:zoop:blk';
    const ENABLE_WEBHOOK_DISCORD_PAGZOOP = 'sub:wh:pay:evt:zoop:en:disc';
    const KEY_MONITOR_PROCESS_WEBHOOK_EVENTS_PAGZOOP = 'sub:wh:pay:evt:zoop:mnt';

    const LIST_ERROR_UNLIMITED_RETRY = 'sub:wh:pay:evt:zoop:err:unl';
    const LIST_ERROR_RETRY = 'sub:wh:pay:evt:zoop:err:retry';
}