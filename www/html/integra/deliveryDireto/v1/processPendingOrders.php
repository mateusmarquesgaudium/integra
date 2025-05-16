<?php

use src\Delivery\Enums\RedisSchema as DeliveryRedisSchema;
use src\DeliveryDireto\Enums\RedisSchema;
use src\DeliveryDireto\Http\Oauth;
use src\DeliveryDireto\Http\OrderDetails;
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
    $maxOrdersAtATime = 30;
    $ordersEvents = $redisClient->lRange(RedisSchema::KEY_LIST_APPROVED_ORDER_EVENTS, 0, $maxOrdersAtATime - 1);
    if (empty($ordersEvents) || !is_array($ordersEvents)) {
        throw new UnderflowException('No orders found');
    }

    $pipeline = [];
    $redisClient->ltrim(RedisSchema::KEY_LIST_APPROVED_ORDER_EVENTS, count($ordersEvents), -1);

    foreach ($ordersEvents as $orderEvent) {
        $orderEvent = json_decode($orderEvent, true);
        try {
            if (!empty($orderEvent['retry'])) {
                $orderEvent['retry']--;
                $pipeline[] = ['rPush', RedisSchema::KEY_LIST_APPROVED_ORDER_EVENTS, json_encode($orderEvent)];
                continue;
            }

            $keyCredential = str_replace('{merchant_id}', $orderEvent['merchant_id'], RedisSchema::KEY_CRENDENTIAL_MERCHANT);
            $credentials = json_decode($redisClient->get($keyCredential), true);
            if (!$credentials) {
                continue;
            }

            $oauth = new Oauth($redisClient, $orderEvent['merchant_id'], $credentials['username'], $credentials['password']);
            $order = new OrderDetails($oauth, $redisClient);
            if (!$order->checkRateLimit($orderEvent['merchant_id'])) {
                $orderEvent['retry'] = 10;
                $pipeline[] = ['rPush', RedisSchema::KEY_LIST_APPROVED_ORDER_EVENTS, json_encode($orderEvent)];
                continue;
            }

            $orderDetails = $order->searchDetails($orderEvent['order_id']);
            if (empty($orderDetails)) {
                $orderEvent['retry'] = 60;
                $pipeline[] = ['rPush', RedisSchema::KEY_LIST_APPROVED_ORDER_EVENTS, json_encode($orderEvent)];
                continue;
            }

            $orderDetails['merchant_id'] = $orderEvent['merchant_id'];
            $formattedOrder = $order->formatOrder($orderDetails);

            $orderEvent['event_details'] = $formattedOrder;
            $pipeline[] = ['rPush', DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, json_encode($orderEvent)];

            $pipeline[] = ['set', $orderEvent['key_order_details'], json_encode([
                'merchant_id' => $orderDetails['merchant_id'],
                'original' => $orderDetails,
                'formatted' => $formattedOrder,
            ]), ['EX' => 60 * 60 * 6]];

            $keyOrdersEventsPending = str_replace('{order_id}', $orderEvent['order_id'], RedisSchema::KEY_LIST_PENDING_ORDER_EVENTS);
            $ordersEventsPending = $redisClient->lRange($keyOrdersEventsPending, 0, -1);
            if (!empty($ordersEventsPending)) {
                $redisClient->ltrim($keyOrdersEventsPending, count($ordersEventsPending), -1);
                foreach ($ordersEventsPending as $event) {
                    $pipeline[] = ['rPush', DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK, $event];
                }
            }
        } catch(\Throwable $e) {
            echo $e->getMessage();
        }
    }
    $redisClient->pipelineCommands($pipeline);
} catch(\UnderflowException $e) {
    echo $e->getMessage();
} catch(\Throwable $t) {
    MchLog::logAndInfo('log_delivery_direto', MchLogLevel::ERROR, [
        'trace' => $t->getTraceAsString(),
        'message' => $t->getMessage(),
    ]);
    echo $t->getMessage();
}

$redisClient->decr(RedisSchema::KEY_MONITOR_PROCESS_PENDING_ORDERS);