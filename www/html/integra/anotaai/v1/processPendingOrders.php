<?php

use src\AnotaAi\Enums\RedisSchema;
use src\AnotaAi\Http\OrderDetails;
use src\geral\Custom;
use src\geral\RedisService;

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

$custom = new Custom;
$redisClient = new RedisService;
$redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxInstances = 1;
$redisClient->checkMonitor(RedisSchema::KEY_MONITOR_PROCESS_PENDING_ORDERS, $maxInstances);
$redisClient->incrMonitor(RedisSchema::KEY_MONITOR_PROCESS_PENDING_ORDERS);

try {
    $maxOrdersAtATime = 100;
    $ordersEvents = $redisClient->lRange(RedisSchema::KEY_LIST_PENDING_ORDER_EVENTS, 0, $maxOrdersAtATime - 1);
    if (empty($ordersEvents) || !is_array($ordersEvents)) {
        throw new UnderflowException('No orders found');
    }

    $pipeline = [];
    $redisClient->ltrim(RedisSchema::KEY_LIST_PENDING_ORDER_EVENTS, count($ordersEvents), -1);

    foreach ($ordersEvents as $orderEvent) {
        $orderEvent = json_decode($orderEvent, true);
        try {
            if (!empty($orderEvent['retry'])) {
                $orderEvent['retry']--;
                $pipeline[] = ['rPush', RedisSchema::KEY_LIST_PENDING_ORDER_EVENTS, json_encode($orderEvent)];
                continue;
            }

            $keyCredential = str_replace('{merchant_id}', $orderEvent['merchant_id'], RedisSchema::KEY_CRENDENTIAL_MERCHANT);
            $credentials = json_decode($redisClient->get($keyCredential), true);
            if (!$credentials) {
                continue;
            }

            $order = new OrderDetails($redisClient, $orderEvent['merchant_id'], $credentials['token']);
            if (!$order->checkRateLimit()) {
                $orderEvent['retry'] = 20;
                $pipeline[] = ['rPush', RedisSchema::KEY_LIST_PENDING_ORDER_EVENTS, json_encode($orderEvent)];
                continue;
            }

            if ($order->orderStillPending($orderEvent['order_id'], $orderEvent, $pipeline)) {
                $orderEvent['retry'] = 10;
                $pipeline[] = ['rPush', RedisSchema::KEY_LIST_PENDING_ORDER_EVENTS, json_encode($orderEvent)];
            }
        } catch(\Throwable $e) {
            echo $e->getMessage();
        }
    }
    $redisClient->pipelineCommands($pipeline);
} catch(\UnderflowException $e) {
    echo $e->getMessage();
} catch(\Throwable $t) {
    MchLog::logAndInfo('log_anotaai', MchLogLevel::ERROR, [
        'trace' => $t->getTraceAsString(),
        'message' => $t->getMessage(),
    ]);
    echo $t->getMessage();
}

$redisClient->decr(RedisSchema::KEY_MONITOR_PROCESS_PENDING_ORDERS);