<?php

namespace src\PagarMe\Enums;

abstract class RedisSchema
{
    const LIST_PAGARME_EVENTS_WEBHOOK = 'sub:wh:pay:evt:pgme:ev';
    const COUNTER_RETRIES_PAGARME = 'sub:wh:pay:evt:pgme:rt';
    const BLOCK_RETRIES_PAGARME = 'sub:wh:pay:evt:pgme:blk';
    const ENABLE_WEBHOOK_DISCORD_PAGARME = 'sub:wh:pay:evt:pgme:en:disc';
    const KEY_MONITOR_PROCESS_WEBHOOK_EVENTS_PAGARME = 'sub:wh:pay:evt:pgme:mnt';

    const LIST_ERROR_UNLIMITED_RETRY = 'sub:wh:pay:evt:pgme:err:unl';
    const LIST_ERROR_RETRY = 'sub:wh:pay:evt:pgme:err:retry';
}