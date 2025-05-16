<?php
require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

use src\PagZoop\Enums\RedisSchema;
use src\PagZoop\Handlers\PagZoopWebhook;
use src\geral\Custom;
use src\geral\RedisService;

$custom = new Custom;
$redisClient = new RedisService;
$redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);
$isBlocked = $redisClient->exists(RedisSchema::BLOCK_RETRIES_PAGZOOP);
if ($isBlocked) {
    return;
}

$maxInstances = 1;
$redisClient->checkMonitor(RedisSchema::KEY_MONITOR_PROCESS_WEBHOOK_EVENTS_PAGZOOP, $maxInstances);
$redisClient->incrMonitor(RedisSchema::KEY_MONITOR_PROCESS_WEBHOOK_EVENTS_PAGZOOP);

try {
    $webhookPagZoop = new PagZoopWebhook($redisClient, $custom);
    $webhookPagZoop->handlerWebhook();
} catch (\UnderflowException $e) {
    echo $e->getMessage();
} catch (\Throwable $t) {
    MchLog::logAndInfo('log_payments', MchLogLevel::ERROR, [
        'trace' => $t->getTraceAsString(),
        'message' => $t->getMessage(),
    ]);
}

$redisClient->decr(RedisSchema::KEY_MONITOR_PROCESS_WEBHOOK_EVENTS_PAGZOOP);
