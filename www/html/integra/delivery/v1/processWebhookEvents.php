<?php

use src\Delivery\Enums\RedisSchema;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

$custom = new Custom;
$redisClient = new RedisService;
$redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxInstances = 1;
$redisClient->checkMonitor(RedisSchema::KEY_MONITOR_PROCESS_WEBHOOK_EVENTS, $maxInstances);
$redisClient->incrMonitor(RedisSchema::KEY_MONITOR_PROCESS_WEBHOOK_EVENTS);

try {
    $maxRetryEvents = 3;
    $maxOrdersAtATime = 50;
    $ordersEvents = $redisClient->lRange(RedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, 0, $maxOrdersAtATime - 1);
    if (empty($ordersEvents) || !is_array($ordersEvents)) {
        throw new UnderflowException('No orders found');
    }

    $pipeline = [];
    $redisClient->ltrim(RedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, count($ordersEvents), -1);

    $events = [];
    foreach ($ordersEvents as $orderEvent) {
        $orderEvent = json_decode($orderEvent, true);
        if (!empty($orderEvent['time_retry'])) {
            $orderEvent['time_retry']--;
            $pipeline[] = ['rPush', RedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, json_encode($orderEvent)];
        } else if (!isset($orderEvent['total_retry']) || $orderEvent['total_retry'] <= $maxRetryEvents) {
            $events[] = $orderEvent;
        } else {
            $pipeline[] = ['rPush', RedisSchema::KEY_PROCESS_ERROR_EVENTS, json_encode($orderEvent)];
        }
    }

    try {
        $request = new Request($custom->getParams('delivery')['url_webhook_orders']);
        $response = $request->setHeaders([
                'Content-Type: application/json',
            ])
            ->setRequestMethod('POST')
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields($events)
            ->execute();
        $content = json_decode($response?->content ?? '', true);

        $retryEvents = [];
        if (empty($content)) {
            $retryEvents = $events;
        } elseif (!empty($content['events_no_success']) && (empty($content['status']) || !$content['status'])) {
            $retryEvents = $content['events_no_success'];
        } elseif ($response?->http_code !== RequestConstants::HTTP_OK) {
            MchLog::logAndInfo('log_delivery', MchLogLevel::ERROR, [
                'title' => 'Error on delivery webhook',
                'content' => $response?->content,
            ]);
            $retryEvents = $events;
        }

        foreach ($retryEvents as $retryEvent) {
            if (!empty($content)) {
                $retryEvent['time_retry'] = 5;
                $retryEvent['total_retry'] = $retryEvent['total_retry'] ?? 0;
                $retryEvent['total_retry']++;
            }
            $pipeline[] = ['rPush', RedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, json_encode($retryEvent)];
        }
    } catch(\Throwable $t) {
        MchLog::logAndInfo('log_delivery', MchLogLevel::ERROR, [
            'trace' => $t->getTraceAsString(),
            'message' => $t->getMessage(),
        ]);
    }

    $redisClient->pipelineCommands($pipeline);
} catch(\UnderflowException $e) {
    echo $e->getMessage();
} catch(\Throwable $t) {
    MchLog::logAndInfo('log_delivery', MchLogLevel::ERROR, [
        'trace' => $t->getTraceAsString(),
        'message' => $t->getMessage(),
    ]);
}

$redisClient->decr(RedisSchema::KEY_MONITOR_PROCESS_WEBHOOK_EVENTS);
