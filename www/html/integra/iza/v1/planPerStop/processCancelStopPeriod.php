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

$totalEvents = 0;
$totalEventsSuccess = 0;
$totalEventsNotInProgress = 0;
$totalEventsNotRetry = 0;
$maxInstances = $custom->getIza()['maxInstancesCancelPeriod'];
$redis->checkMonitor(IzaVariables::KEY_MONITOR_CANCEL_STOP_PERIOD, $maxInstances);
$redis->incrMonitor(IzaVariables::KEY_MONITOR_CANCEL_STOP_PERIOD);

$maxEventsAtATime = $custom->getIza()['maxEventsAtATime'];
$events = $redis->lRange(IzaVariables::KEY_EVENTS_CANCEL_STOP_PERIOD, 0, $maxEventsAtATime - 1);
if (!empty($events) && is_array($events)) {
    $redis->ltrim(IzaVariables::KEY_EVENTS_CANCEL_STOP_PERIOD, count($events), -1);

    $pipelineRetry = [];
    $intervalEventsRetry = $custom->getIza()['minutesIntervalForEventsRetry'];

    $requestMulti = new RequestMulti;
    $urlIza = $custom->getIza()['url'];

    // Inicializa os requests
    foreach ($events as $key => &$event) {
        $event = json_decode($event, true);

        if (!empty($event['last_attempt']) && strtotime('now') <= strtotime($event['last_attempt'] . ' +' . $intervalEventsRetry . ' minutes')) {
            $pipelineRetry[] = ['rPush', IzaVariables::KEY_EVENTS_CANCEL_STOP_PERIOD, json_encode($event)];
            $totalEventsNotRetry++;
            unset($events[$key]);
            continue;
        }

        $event['index'] = $event['taxista_id'] . $event['solicitacao_id'] . ':' . $event['parada'];
        $event['attempts'] = ($event['attempts'] ?? 0) + 1;

        $period = $redis->get(IzaVariables::KEY_EVENTS_IN_PROGRESS_STOP_PERIOD . $event['index']);
        if (empty($period)) {
            $event['data_processo'] = $event['data_processo'] ?? date('Y-m-d H:i:s');
            if (strtotime('now') <= strtotime($event['data_processo'] . ' +' . IzaVariables::DAYS_FOR_EXPIRE_EVENTS_RETRY . ' days')) {
                $event['attempts'] = 0;
                $pipelineRetry[] = ['rPush', IzaVariables::KEY_EVENTS_CANCEL_STOP_PERIOD, json_encode($event)];
            }
            $totalEventsNotInProgress++;
            unset($events[$key]);
            continue;
        }

        $period = json_decode($period, true);
        $request = new Request($urlIza . '/intermittent/persons/periods/' . $period['id'] . '/cancel');
        $request
            ->setHeaders([
                'Content-Type: application/json',
                'Authorization: Basic ' . $period['chave_integracao'],
            ])
            ->setRequestMethod('PUT')
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields([
                'finished_at' => '',
            ]);
        $requestMulti->setRequest($event['index'], $request);
    }
    $redis->pipelineCommands($pipelineRetry);

    if ($requestMulti->haveRequests()) {
        // Executa os requests
        $response = $requestMulti->execute();

        $pipeline = [];
        $maxAttemptsRequest = $custom->getIza()['maxAttemptsRequest'];
        // Percore os events buscando o request retornado pela IZA
        foreach ($events as $eventResult) {
            $request = $response[$eventResult['index']] ?? [];
            $content = json_decode($request?->content ?? '', true);
            $httpCode = $request?->http_code ?? null;

            if (!empty($request) && in_array($httpCode, [RequestConstants::HTTP_OK, RequestConstants::HTTP_CREATED, RequestConstants::HTTP_ACCEPTED]) && !empty($content['status']) && $content['status'] == 'cancelled') {
                // Deleta possíveis resíduos do redis (eventos que ficam)
                $totalEventsSuccess++;
                $pipeline[] = ['del', IzaVariables::KEY_EVENTS_IN_PROGRESS_STOP_PERIOD . $eventResult['index']];
            } elseif (isset($content['errors'], $content['errors']['details']) && $content['errors']['details'] == IzaVariables::ERROR_CANCELATION_OUT_OF_TIME_LIMIT) {
                unset($eventResult['attempts'], $eventResult['last_attempt']);
                $pipeline[] = ['rPush', IzaVariables::KEY_EVENTS_FINISH_STOP_PERIOD, json_encode($eventResult)];
            } elseif ($eventResult['attempts'] >= $maxAttemptsRequest) {
                $eventResult['error'] = $content;
                $eventResult['httpCode'] = $httpCode;
                $pipeline[] = ['lPush', IzaVariables::KEY_EVENTS_ERROR_CANCEL_STOP_PERIOD, json_encode($eventResult)];
            } else {
                $eventResult['last_attempt'] = date('Y-m-d H:i:s');
                $pipeline[] = ['rPush', IzaVariables::KEY_EVENTS_CANCEL_STOP_PERIOD, json_encode($eventResult)];
            }
        }
        $redis->pipelineCommands($pipeline);
    }
}
$redis->decr(IzaVariables::KEY_MONITOR_CANCEL_STOP_PERIOD);

Util::sendJson([
    'total_events' => $totalEvents,
    'total_events_success' => $totalEventsSuccess,
    'total_events_not_in_progress' => $totalEventsNotInProgress,
    'total_events_not_retry' => $totalEventsNotRetry,
]);
