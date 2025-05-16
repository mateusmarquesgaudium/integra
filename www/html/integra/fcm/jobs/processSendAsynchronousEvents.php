<?php

use src\Fcm\Enums\RedisSchema;
use src\Fcm\Http\SendAsynchronousEvents;
use src\geral\Custom;
use src\geral\RedisService;

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

$custom = new Custom;
$redisClient = new RedisService;
$redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxOrdersAtATime = 5;

$maxProcess = $redisClient->get(RedisSchema::KEY_MAX_PROCESS_SEND_EVENTS) ?: 1;
if (!$redisClient->incrMonitorWithLimit(RedisSchema::KEY_MONITOR_PROCESS_SEND_EVENTS, $maxProcess)) {
    exit;
}

try {
    for ($i = 0; $i < $maxOrdersAtATime; $i++) {
        $dataEvents = json_decode($redisClient->lPop(RedisSchema::KEY_LIST_EVENTS_FCM), true);
        if (empty($dataEvents) || !is_array($dataEvents) || !isset($dataEvents['requests'], $dataEvents['path'], $dataEvents['accessToken'])) {
            break;
        }

        $sendAsynchronousEvents = new SendAsynchronousEvents($custom);
        $sendAsynchronousEvents->send($dataEvents['path'], $dataEvents['accessToken'], $dataEvents['requests']);
    }
} catch(\Throwable $t) {
    MchLog::log(MchLogLevel::ERROR, [
        'trace' => $t->getTraceAsString(),
        'message' => $t->getMessage(),
    ]);
} finally {
    $redisClient->decr(RedisSchema::KEY_MONITOR_PROCESS_SEND_EVENTS);
}
