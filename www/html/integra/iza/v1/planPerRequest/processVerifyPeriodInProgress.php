<?php
require_once __DIR__ . '/../../../../../src/autoload.php';

use src\geral\ {
    Custom,
    RedisService,
    Request,
    Util
};
use src\geral\Enums\RequestConstants;
use src\iza\IzaVariables;

$custom = new Custom;

$redis = new RedisService;
$redis->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$totalEvents = 0;

$maxInstances = 1;
$redis->checkMonitor(IzaVariables::KEY_MONITOR_VERIFY_PERIOD_IN_PROGRESS, $maxInstances);
$redis->incrMonitor(IzaVariables::KEY_MONITOR_VERIFY_PERIOD_IN_PROGRESS);

$currentDateTime = Util::currentDateTime('America/Sao_Paulo');
$periods = [];

$minimiumHourForVerify = 5;

$scanCursor = null;
$scanSize = 500;
$scanPattern = IzaVariables::KEY_EVENTS_IN_PROGRESS_PERIOD . '*';

$redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
while ($keys = $redis->scan($scanCursor, $scanPattern, $scanSize)) {
    $values = $redis->mget($keys);
    if (empty($values)) {
        continue;
    }
    foreach ($values as $value) {
        $data = json_decode($value, true);
        if (!$data || empty($data['data_criacao']) || !isset($data['solicitacao_id'], $data['taxista_id'])) {
            continue;
        }

        $creationDate = new DateTime($data['data_criacao'], new DateTimeZone('America/Sao_Paulo'));
        $interval = $currentDateTime->diff($creationDate);
        if ($interval->h < $minimiumHourForVerify) {
            continue;
        }

        $periods[$data['solicitacao_id']] = [
            'solicitacao_id' => $data['solicitacao_id'],
            'taxista_id' => $data['taxista_id'],
        ];
        $totalEvents++;
    }
}


if (!empty($periods)) {
    $contentRequest = [
        'periods' => array_values($periods),
    ];

    $request = new Request($custom->getIza()['url_verify_period']);
    $response = $request->setHeaders([
            'Content-Type: application/json',
            'Authorization: Basic ' . $custom->getIza()['token_verify_period'],
        ])
        ->setRequestMethod('POST')
        ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
        ->setPostFields($contentRequest)
        ->execute();
}
$redis->decr(IzaVariables::KEY_MONITOR_VERIFY_PERIOD_IN_PROGRESS);

Util::sendJson([
    'total_events' => $totalEvents,
]);
