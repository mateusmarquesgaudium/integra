<?php

use src\Delivery\Enums\Provider;
use src\Delivery\Enums\RedisSchema;
use src\DeliveryDireto\Http\OrderFinished as DeliveryDiretoOrderFinished;
use src\AnotaAi\Http\OrderFinished as AnotaAiOrderFinished;
use src\Neemo\Http\OrderFinished as NeemoOrderFinished;
use src\Opendelivery\Handlers\OrderFinished as OpendeliveryOrderFinished;
use src\Aiqfome\Http\OrderFinished as AiqfomeOrderFinished;
use src\geral\Custom;
use src\geral\RedisService;
use src\Opendelivery\Enums\ProvidersOpenDelivery;

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

$custom = new Custom;
$redisClient = new RedisService;
$redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxInstances = 1;
$redisClient->checkMonitor(RedisSchema::KEY_MONITOR_PROCESS_FINISHED_EVENTS, $maxInstances);
$redisClient->incrMonitor(RedisSchema::KEY_MONITOR_PROCESS_FINISHED_EVENTS);

try {
    $maxOrdersAtATime = 50;
    $finishedEvents = $redisClient->lRange(RedisSchema::KEY_LIST_ORDERS_EVENTS_FINISHED, 0, $maxOrdersAtATime - 1);
    if (empty($finishedEvents) || !is_array($finishedEvents)) {
        throw new UnderflowException('No finished events found');
    }

    $pipeline = [];
    $redisClient->ltrim(RedisSchema::KEY_LIST_ORDERS_EVENTS_FINISHED, count($finishedEvents), -1);

    try {
        foreach ($finishedEvents as $finishedEvent) {
            $finishedEvent = json_decode($finishedEvent, true);
            if (empty($finishedEvent) || empty($finishedEvent['provider'])) {
                continue;
            }

            $orderFinished = null;
            if ($finishedEvent['provider'] === Provider::DELIVERY_DIRETO) {
                $orderFinished = new DeliveryDiretoOrderFinished($redisClient);
            } else if ($finishedEvent['provider'] === Provider::ANOTAAI) {
                $orderFinished = new AnotaAiOrderFinished($redisClient);
            } else if ($finishedEvent['provider'] === Provider::NEEMO) {
                $orderFinished = new NeemoOrderFinished($redisClient);
            } else if (ProvidersOpenDelivery::isValidProvider($finishedEvent['provider'])) {
                $orderFinished = new OpendeliveryOrderFinished($redisClient, $custom);
            } else if ($inTransitEvent['provider'] === Provider::AIQFOME) {
                $orderFinished = new AiqfomeOrderFinished($redisClient, $custom);
            }

            if (!$orderFinished) {
                continue;
            }

            if (!$orderFinished->send($finishedEvent, $pipeline)) {
                $pipeline[] = ['rPush', RedisSchema::KEY_LIST_ORDERS_EVENTS_FINISHED, json_encode($finishedEvent)];
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

$redisClient->decr(RedisSchema::KEY_MONITOR_PROCESS_FINISHED_EVENTS);
