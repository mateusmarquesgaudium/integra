<?php
require_once __DIR__ . '/../../../../../src/autoload.php';

use src\geral\ {
    Custom,
    RedisService,
    Request,
    RequestMulti,
    Util
};
use src\geral\Enums\RequestConstants;
use src\iza\IzaVariables;

$custom = new Custom;

$redis = new RedisService;
$redis->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxInstances = $custom->getIza()['maxInstancesSendPosition'];
$redis->checkMonitor(IzaVariables::KEY_MONITOR_SEND_POSITION, $maxInstances);
$redis->incrMonitor(IzaVariables::KEY_MONITOR_SEND_POSITION);

$totalEventsExecuted = 0;
$maxEventsAtATimePosition = $custom->getIza()['maxEventsAtATimePosition'];

$listPositions = $redis->lRange(IzaVariables::KEY_EVENTS_POSITION_PERSON, 0, -1);
$totalPosition = count($listPositions);
if ($totalPosition > 0) {
    $redis->ltrim(IzaVariables::KEY_EVENTS_POSITION_PERSON, $totalPosition, -1);

    $listPositions = array_chunk($listPositions, $maxEventsAtATimePosition);

    foreach ($listPositions as $positionChunks) {
        $requestMulti = new RequestMulti;

        foreach ($positionChunks as $index => $positionChunk) {
            $positionChunk = json_decode($positionChunk, true);
            if (!$positionChunk || empty($positionChunk['chave_integracao'])) {
                continue;
            }

            $request = new Request($custom->getIza()['url'] . '/intermittent/persons/geolocation');
            $request
                ->setHeaders([
                    'Content-Type: application/json',
                    'Authorization: Basic ' . $positionChunk['chave_integracao'],
                ])
                ->setRequestMethod('POST')
                ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
                ->setPostFields([
                    'doc' => $positionChunk['doc'],
                    'datetime' => $positionChunk['data_hora'],
                    'lat' => $positionChunk['lat'],
                    'long' => $positionChunk['lng']
                ]);
            $requestMulti->setRequest("position$index", $request);
            $totalEventsExecuted++;
        }

        if ($requestMulti->haveRequests()) {
            // Executa os requests
            $requestMulti->execute();
        }
    }
}

$redis->decr(IzaVariables::KEY_MONITOR_SEND_POSITION);

Util::sendJson([
    'total_position' => $totalPosition,
    'total_events_executed' => $totalEventsExecuted,
]);
