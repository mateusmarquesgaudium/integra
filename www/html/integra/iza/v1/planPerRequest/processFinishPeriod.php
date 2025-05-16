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

$maxInstances = $custom->getIza()['maxInstancesFinishPeriod'];
$redis->checkMonitor(IzaVariables::KEY_MONITOR_FINISH_PERIOD, $maxInstances);
$redis->incrMonitor(IzaVariables::KEY_MONITOR_FINISH_PERIOD);

$totalEvents = 0;
$totalEventsSuccess = 0;
$totalEventsNotInProgress = 0;
$totalEventsNotRetry = 0;
$maxEventsAtATime = $custom->getIza()['maxEventsAtATime'];
$events = $redis->lRange(IzaVariables::KEY_EVENTS_FINISH_PERIOD, 0, $maxEventsAtATime - 1);
if (!empty($events) && is_array($events)) {
    $totalEvents = count($events);
    $redis->ltrim(IzaVariables::KEY_EVENTS_FINISH_PERIOD, $totalEvents, -1);

    $pipelineRetry = [];
    $intervalEventsRetry = $custom->getIza()['minutesIntervalForEventsRetry'];

    $requestMulti = new RequestMulti;
    $urlIza = $custom->getIza()['url'];

    // Inicializa os requests
    foreach ($events as $key => &$event) {
        $event = json_decode($event, true);

        if (!empty($event['last_attempt']) && strtotime('now') <= strtotime($event['last_attempt'] . ' +' . $intervalEventsRetry . ' minutes')) {
            $pipelineRetry[] = ['rPush', IzaVariables::KEY_EVENTS_FINISH_PERIOD, json_encode($event)];
            $totalEventsNotRetry++;
            unset($events[$key]);
            continue;
        }

        $event['index'] = $event['taxista_id'] . $event['solicitacao_id'];
        $event['attempts'] = ($event['attempts'] ?? 0) + 1;

        $period = $redis->get(IzaVariables::KEY_EVENTS_IN_PROGRESS_PERIOD . $event['index']);
        if (empty($period)) {
            $event['data_processo'] = $event['data_processo'] ?? date('Y-m-d H:i:s');
            if (strtotime('now') <= strtotime($event['data_processo'] . ' +' . IzaVariables::DAYS_FOR_EXPIRE_EVENTS_RETRY . ' days')) {
                $event['attempts'] = 0;
                $pipelineRetry[] = ['rPush', IzaVariables::KEY_EVENTS_FINISH_PERIOD, json_encode($event)];
            }
            $totalEventsNotInProgress++;
            unset($events[$key]);
            continue;
        }

        $period = json_decode($period, true);
        $request = new Request($urlIza . '/intermittent/persons/periods/' . $period['id']);
        $request
            ->setHeaders([
                'Content-Type: application/json',
                'Authorization: Basic ' . $period['chave_integracao'],
            ])
            ->setRequestMethod('PUT')
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields([
                'finished_at' => $event['data_hora'] ?? Util::currentDateTime('America/Sao_Paulo')->format('Y-m-d H:i:s'),
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

            if (
                !empty($request)
                && in_array($httpCode, [RequestConstants::HTTP_OK, RequestConstants::HTTP_CREATED, RequestConstants::HTTP_ACCEPTED])
                && !empty($content['status']) && $content['status'] == 'active'
                || isset($content['errors'], $content['errors']['details']) && $content['errors']['details'] == 'already_finished_period'
            ) {
                // Deleta possíveis resíduos do redis (eventos que ficam)
                $totalEventsSuccess++;
                $pipeline[] = ['del', IzaVariables::KEY_EVENTS_IN_PROGRESS_PERIOD . $eventResult['index']];
            } elseif (
                $httpCode == RequestConstants::HTTP_UNPROCESSABLE_ENTITY
                && !empty($event['data_hora'])
                && isset($content['errors'], $content['errors']['finished_at'])
                && $content['errors']['finished_at'] == IzaVariables::ERROR_FINISHED_AT_MUST_COME_AFTER_STARTED_AT
            ) {
                $dateTime = new DateTime($eventResult['data_hora']);
                $dateTime->modify('+1 second');

                $eventResult['data_hora'] = $dateTime->format('Y-m-d H:i:s');
                $eventResult['attempts']--;
                $pipeline[] = ['rPush', IzaVariables::KEY_EVENTS_FINISH_PERIOD, json_encode($eventResult)];
            } elseif ($eventResult['attempts'] >= $maxAttemptsRequest) {
                $eventResult['error'] = $content;
                $eventResult['httpCode'] = $httpCode;
                $pipeline[] = ['lPush', IzaVariables::KEY_EVENTS_ERROR_FINISH_PERIOD, json_encode($eventResult)];
            } else {
                $eventResult['last_attempt'] = date('Y-m-d H:i:s');
                $pipeline[] = ['rPush', IzaVariables::KEY_EVENTS_FINISH_PERIOD, json_encode($eventResult)];
            }
        }
        $redis->pipelineCommands($pipeline);
    }
}
$redis->decr(IzaVariables::KEY_MONITOR_FINISH_PERIOD);

Util::sendJson([
    'total_events' => $totalEvents,
    'total_events_success' => $totalEventsSuccess,
    'total_events_not_in_progress' => $totalEventsNotInProgress,
    'total_events_not_retry' => $totalEventsNotRetry,
]);
