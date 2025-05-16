<?php
require_once __DIR__ . '/../../../../../src/autoload.php';

use src\geral\ {
    RedisService,
    Util,
    Custom,
    Request,
};
use src\geral\Enums\RequestConstants;
use src\iza\IzaVariables;

$custom = new Custom;
$configWebhook = $custom->getIza()['webhook'];

$redis = new RedisService;
$redis->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$dateMonitor = $redis->get(IzaVariables::KEY_DATE_MONITOR_ERRORS);
if (!empty($dateMonitor) && strtotime(date('Y-m-d H:i:s')) <= strtotime($dateMonitor . ' +24 hours')) {
    Util::sendJson([
        'status' => false, 
        'message' => 'Error monitor has already been run today.'
    ]);
}
if (!empty($dateMonitor)) {
    $redis->del(IzaVariables::KEY_DATE_MONITOR_ERRORS);
}

$resultado = [
    'status' => true,
];

$simpleProcessesQuantity = 30;
$numberImportantProcesses = 10;

// TODO: Ver necessidade de colocar as novas chaves no check errors, ou fazer mesclagem

$createPeriod = (int) $redis->lLen(IzaVariables::KEY_EVENTS_ERROR_CREATE_PERIOD);
$cancelPeriod = (int) $redis->lLen(IzaVariables::KEY_EVENTS_ERROR_CANCEL_PERIOD);
$finishPeriod = (int) $redis->lLen(IzaVariables::KEY_EVENTS_ERROR_FINISH_PERIOD);
$createContract = (int) $redis->lLen(IzaVariables::KEY_EVENTS_ERROR_CREATE_CONTRACT);
$searchContract = (int) $redis->lLen(IzaVariables::KEY_EVENTS_ERROR_SEARCH_CONTRACT);

$createPeriod = $createPeriod >= $simpleProcessesQuantity ? $createPeriod : 0;
$cancelPeriod = $cancelPeriod >= $numberImportantProcesses ? $cancelPeriod : 0;
$finishPeriod = $finishPeriod >= $numberImportantProcesses ? $finishPeriod : 0;
$createContract = $createContract >= $simpleProcessesQuantity ? $createContract : 0;
$searchContract = $searchContract >= $simpleProcessesQuantity ? $searchContract : 0;

$total = $createPeriod + $cancelPeriod + $finishPeriod + $createContract + $searchContract;
if ($total > 0) {
    $resultado = [
        'status' => true,
        'create_period' => $createPeriod,
        'cancel_period' => $cancelPeriod,
        'finish_period' => $finishPeriod,
        'create_contract' => $createContract,
        'search_contract' => $searchContract,
        'total' => $total,
    ];

    $request = new Request($configWebhook['url_error']);
    $response = $request->setHeaders([
            'Content-Type: application/json',
            'Authorization: Basic ' . $configWebhook['token_error'],
        ])
        ->setRequestMethod('POST')
        ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
        ->setPostFields($resultado)
        ->execute();
    $content = json_decode($response?->content ?? '', true);
    $httpCode = $response?->http_code ?? null;

    if (!empty($content) && in_array($httpCode, [RequestConstants::HTTP_OK])) {
        $redis->set(IzaVariables::KEY_DATE_MONITOR_ERRORS, date('Y-m-d H:i:s'));
    }
}
Util::sendJson($resultado);
