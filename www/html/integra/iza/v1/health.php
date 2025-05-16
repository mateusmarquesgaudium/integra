<?php
require_once __DIR__ . '/../../../../src/autoload.php';

use src\geral\ {
    RedisService,
    Util,
    Custom,
};
use src\iza\IzaVariables;

$custom = new Custom;

$redis = new RedisService;
$redis->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$resultado = [];

// TODO: Ver necessidade de colocar as novas chaves no health

// Monitor
$resultado['monitors'] = [
    'create_person' => $redis->get(IzaVariables::KEY_MONITOR_CREATE_PERSON),
    'search_contract' => $redis->get(IzaVariables::KEY_MONITOR_SEARCH_CONTRACT),
    'create_period' => $redis->get(IzaVariables::KEY_MONITOR_CREATE_PERIOD),
    'cancel_period' => $redis->get(IzaVariables::KEY_MONITOR_CANCEL_PERIOD),
    'finish_period' => $redis->get(IzaVariables::KEY_MONITOR_FINISH_PERIOD),
    'send_position' => $redis->get(IzaVariables::KEY_MONITOR_SEND_POSITION),
    'webhook_person' => $redis->get(IzaVariables::KEY_MONITOR_WEBHOOK_PERSON),
    'webhook_period' => $redis->get(IzaVariables::KEY_MONITOR_WEBHOOK_PERIOD),
];

// Events
$resultado['events'] = [
    'create_person' => $redis->lLen(IzaVariables::KEY_EVENTS_CREATE_PERSON),
    'search_contract' => $redis->lLen(IzaVariables::KEY_EVENTS_SEARCH_CONTRACT),
    'create_period' => $redis->lLen(IzaVariables::KEY_EVENTS_CREATE_PERIOD),
    'cancel_period' => $redis->lLen(IzaVariables::KEY_EVENTS_CANCEL_PERIOD),
    'finish_period' => $redis->lLen(IzaVariables::KEY_EVENTS_FINISH_PERIOD),
    'in_progress' => count($redis->keys(IzaVariables::KEY_EVENTS_IN_PROGRESS_PERIOD . '*')),
];

// Webhooks
$resultado['webhooks'] = [
    'create_person' => $redis->sCard(IzaVariables::KEY_WEBHOOK_CREATE_PERSON),
    'create_period' => $redis->sCard(IzaVariables::KEY_WEBHOOK_CREATE_PERIOD),
];

// Erros
$resultado['errors'] = [
    'create_person' => count($redis->keys(IzaVariables::KEY_EVENTS_ERROR_CREATE_PERSON . '*')),
    'search_contract' => $redis->lLen(IzaVariables::KEY_EVENTS_ERROR_SEARCH_CONTRACT),
    'create_period' => $redis->lLen(IzaVariables::KEY_EVENTS_ERROR_CREATE_PERIOD),
    'cancel_period' => $redis->lLen(IzaVariables::KEY_EVENTS_ERROR_CANCEL_PERIOD),
    'finish_period' => $redis->lLen(IzaVariables::KEY_EVENTS_ERROR_FINISH_PERIOD),
    'create_contract' => $redis->lLen(IzaVariables::KEY_EVENTS_ERROR_CREATE_CONTRACT),
];

Util::sendJson($resultado);
