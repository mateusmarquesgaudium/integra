<?php

use src\ifood\Enums\RedisSchema;
use src\geral\Custom;
use src\geral\RedisService;
use src\ifood\Entities\EventsIfoodFactory;
use src\ifood\Enums\OauthMode;
use src\ifood\Http\Oauth;

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

$custom = new Custom;
$redisClient = new RedisService;
$redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxEventRetry = 10;
$maxOrdersAtATime = 50;
$timeToRetry = 10;

$maxProcess = $redisClient->get(RedisSchema::KEY_MAX_PROCESS_ORDER_EVENTS) ?: 1;
if (!$redisClient->incrMonitorWithLimit(RedisSchema::KEY_MONITOR_PROCESS_ORDER_EVENTS, $maxProcess)) {
    echo 'Máximo de processos em execução';
    exit;
}

try {
    $oauth = new Oauth($redisClient, OauthMode::WEBHOOK);
    $pipeline = [];

    $useWebhook = $redisClient->get(RedisSchema::KEY_ENABLE_WEBHOOK);
    if (!empty($useWebhook)) {
        for ($i = 0; $i < $maxOrdersAtATime; $i++) {
            $ifoodEvent = $redisClient->lPop(RedisSchema::KEY_LIST_ORDERS_EVENTS);
            if (empty($ifoodEvent)) {
                break;
            }

            $ifoodEvent = json_decode($ifoodEvent, true);
            if (empty($ifoodEvent) || empty($ifoodEvent['provider']) || empty($ifoodEvent['orderId'])) {
                continue;
            }

            try {
                if (isset($ifoodEvent['timeToRetry']) && $ifoodEvent['timeToRetry'] > time()) {
                    $pipeline[] = ['rPush', RedisSchema::KEY_LIST_ORDERS_EVENTS, json_encode($ifoodEvent)];
                    continue;
                }

                if (!empty($ifoodEvent['retry']) && $ifoodEvent['retry'] >= $maxEventRetry) {
                    MchLog::logAndInfo('log_ifood_events', MchLogLevel::INFO, [
                        'message' => 'Max retry reached',
                        'event' => $ifoodEvent,
                    ]);
                    continue;
                }

                $entityManager = EventsIfoodFactory::create($ifoodEvent['webhookType'], $oauth, $redisClient);
                if (!$entityManager->send($ifoodEvent)) {
                    $ifoodEvent['timeToRetry'] = time() + $timeToRetry;
                    $ifoodEvent['retry'] = ($ifoodEvent['retry'] ?? 0) + 1;
                    $pipeline[] = ['rPush', RedisSchema::KEY_LIST_ORDERS_EVENTS, json_encode($ifoodEvent)];
                }
            } catch(\Throwable $t) {
                $pipeline[] = ['rPush', RedisSchema::KEY_LIST_ORDERS_EVENTS, json_encode($ifoodEvent)];
                MchLog::logAndInfo('log_ifood_events', MchLogLevel::ERROR, [
                    'trace' => $t->getTraceAsString(),
                    'message' => $t->getMessage(),
                ]);
            }
        }
    }

    $redisClient->pipelineCommands($pipeline);
} catch(\UnderflowException $e) {
    echo $e->getMessage();
} catch(\Throwable $t) {
    MchLog::logAndInfo('log_ifood', MchLogLevel::ERROR, [
        'trace' => $t->getTraceAsString(),
        'message' => $t->getMessage(),
    ]);
} finally {
    $redisClient->decr(RedisSchema::KEY_MONITOR_PROCESS_ORDER_EVENTS);
}
