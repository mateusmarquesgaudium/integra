<?php

use src\Delivery\Enums\Provider;
use src\Delivery\Enums\RedisSchema;
use src\DeliveryDireto\Http\OrderInTransit as DeliveryDiretoOrderInTransit;
use src\AnotaAi\Http\OrderInTransit as AnotaAiOrderInTransit;
use src\Neemo\Http\OrderInTransit as NeemoOrderInTransit;
use src\Opendelivery\Handlers\OrderInTransit as OpendeliveryOrderInTransit;
use src\Aiqfome\Http\OrderInTransit as AiqfomeOrderInTransit;
use src\geral\Custom;
use src\geral\RedisService;
use src\Opendelivery\Enums\ProvidersOpenDelivery;

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

$custom = new Custom;
$redisClient = new RedisService;
$redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxInstances = 1;
$redisClient->checkMonitor(RedisSchema::KEY_MONITOR_PROCESS_IN_TRANSIT_EVENTS, $maxInstances);
$redisClient->incrMonitor(RedisSchema::KEY_MONITOR_PROCESS_IN_TRANSIT_EVENTS);

try {
    $maxOrdersAtATime = 50;
    $inTransitEvents = $redisClient->lRange(RedisSchema::KEY_LIST_ORDERS_EVENTS_IN_TRANSIT, 0, $maxOrdersAtATime - 1);
    if (empty($inTransitEvents) || !is_array($inTransitEvents)) {
        throw new UnderflowException('No in transit events found');
    }

    $pipeline = [];
    $redisClient->ltrim(RedisSchema::KEY_LIST_ORDERS_EVENTS_IN_TRANSIT, count($inTransitEvents), -1);

    try {
        foreach ($inTransitEvents as $inTransitEvent) {
            $inTransitEvent = json_decode($inTransitEvent, true);
            if (empty($inTransitEvent) || empty($inTransitEvent['provider'])) {
                continue;
            }

            $orderInTransit = null;
            if ($inTransitEvent['provider'] === Provider::DELIVERY_DIRETO) {
                $orderInTransit = new DeliveryDiretoOrderInTransit($redisClient);
            } else if ($inTransitEvent['provider'] === Provider::ANOTAAI) {
                $orderInTransit = new AnotaAiOrderInTransit($redisClient);
            } else if ($inTransitEvent['provider'] === Provider::NEEMO) {
                $orderInTransit = new NeemoOrderInTransit($redisClient);
            }  else if (ProvidersOpenDelivery::isValidProvider($inTransitEvent['provider'])) {
                $orderInTransit = new OpendeliveryOrderInTransit($redisClient, $custom);
            } else if ($inTransitEvent['provider'] === Provider::AIQFOME) {
                $orderInTransit = new AiqfomeOrderInTransit($redisClient, $custom);
            }

            if (!$orderInTransit) {
                continue;
            }

            if (!$orderInTransit->send($inTransitEvent)) {
                $pipeline[] = ['rPush', RedisSchema::KEY_LIST_ORDERS_EVENTS_IN_TRANSIT, json_encode($inTransitEvent)];
            }
        }
    } catch(\Throwable $t) {
        MchLog::info('log_delivery', [
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

$redisClient->decr(RedisSchema::KEY_MONITOR_PROCESS_IN_TRANSIT_EVENTS);
