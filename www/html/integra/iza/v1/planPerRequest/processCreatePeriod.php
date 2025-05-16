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

$maxInstances = $custom->getIza()['maxInstancesCreatePeriod'];
$redis->checkMonitor(IzaVariables::KEY_MONITOR_CREATE_PERIOD, $maxInstances);
$redis->incrMonitor(IzaVariables::KEY_MONITOR_CREATE_PERIOD);

$totalEvents = 0;
$totalEventsSuccess = 0;
$totalEventsNotRetry = 0;
$maxEventsAtATime = $custom->getIza()['maxEventsAtATime'];
$events = $redis->lRange(IzaVariables::KEY_EVENTS_CREATE_PERIOD, 0, $maxEventsAtATime - 1);
if (!empty($events) && is_array($events)) {
    $totalEvents = count($events);
    $redis->ltrim(IzaVariables::KEY_EVENTS_CREATE_PERIOD, $totalEvents, -1);

    $pipelineRetry = [];
    $intervalEventsRetry = $custom->getIza()['minutesIntervalForEventsRetry'];

    $requestMulti = new RequestMulti;
    $urlIza = $custom->getIza()['url'];

    // Inicializa os requests
    foreach ($events as $key => &$event) {
        $event = json_decode($event, true);

        if (!empty($event['last_attempt']) && strtotime('now') <= strtotime($event['last_attempt'] . ' +' . $intervalEventsRetry . ' minutes')) {
            $pipelineRetry[] = ['rPush', IzaVariables::KEY_EVENTS_CREATE_PERIOD, json_encode($event)];
            $totalEventsNotRetry++;
            unset($events[$key]);
            continue;
        }
        
        $event['index'] = $event['taxista_id'] . $event['solicitacao_id'];
        $event['attempts'] = ($event['attempts'] ?? 0) + 1;

        $request = new Request($urlIza . '/intermittent/persons/periods');
        $request
            ->setHeaders([
                'Content-Type: application/json',
                'Authorization: Basic ' . $event['chave_integracao'],
            ])
            ->setRequestMethod('POST')
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields([
                'doc' => $event['doc'],
                'started_at' => $event['data_hora'],
                'finished_at' => '',
            ]);
        $requestMulti->setRequest($event['index'], $request);
    }
    $redis->pipelineCommands($pipelineRetry);

    // Executa os requests
    $response = $requestMulti->execute();

    $pipeline = [];
    $maxAttemptsRequest = $custom->getIza()['maxAttemptsRequest'];
    // Percore os events buscando o request retornado pelo IZA
    foreach ($events as $eventResult) {
        $request = $response[$eventResult['index']] ?? [];
        $content = json_decode($request?->content ?? '', true);
        $httpCode = $request?->http_code ?? null;
        $removeCompanyInQueueForbiddenRequest = true;

        if (!empty($request) && in_array($httpCode, [RequestConstants::HTTP_OK, RequestConstants::HTTP_CREATED, RequestConstants::HTTP_ACCEPTED]) && !empty($content['id'])) {
            $inProgress = [
                'id' => $content['id'],
                'taxista_id' => $eventResult['taxista_id'],
                'solicitacao_id' => $eventResult['solicitacao_id'],
                'doc' => $eventResult['doc'],
                'chave_integracao' => $eventResult['chave_integracao'],
                'data_criacao' => Util::currentDateTime('America/Sao_Paulo')->format('Y-m-d H:i:s'),
            ];
            $pipeline[] = ['set', IzaVariables::KEY_EVENTS_IN_PROGRESS_PERIOD . $eventResult['index'], json_encode($inProgress)];

            $webhook = [
                'id' => $eventResult['index'],
                'periodo_id' => $inProgress['id'],
                'solicitacao_id' => $eventResult['solicitacao_id'],
            ];
            $pipeline[] = ['sAdd', IzaVariables::KEY_WEBHOOK_CREATE_PERIOD, json_encode($webhook)];
            $totalEventsSuccess++;

            $pipeline[] = ['del', IzaVariables::KEY_EVENTS_ERROR_CREATE_PERSON . $eventResult['bandeira_id'] . ':' . $eventResult['taxista_id']];
        } elseif (isset($content['errors'], $content['errors']['detail']) && $content['errors']['detail'] == IzaVariables::ERROR_NOT_FOUND) {
            unset($eventResult['attempts'], $eventResult['last_attempt']);
            $pipeline[] = ['rPush', IzaVariables::KEY_EVENTS_CREATE_PERSON, json_encode($eventResult)];
        } elseif ($eventResult['attempts'] >= $maxAttemptsRequest) {
            $eventResult['error'] = $content; 
            $eventResult['httpCode'] = $httpCode; 
            $pipeline[] = ['lPush', IzaVariables::KEY_EVENTS_ERROR_CREATE_PERIOD, json_encode($eventResult)];
        } elseif ($httpCode == RequestConstants::HTTP_FORBIDDEN) {
            $removeCompanyInQueueForbiddenRequest = false;
            $companyIsInQueueForbiddenRequest = $redis->zRank(IzaVariables::KEY_EVENTS_FORBIDDEN_REQUEST, $eventResult['bandeira_id']);
            if ($companyIsInQueueForbiddenRequest === false) {
                $score = strtotime('now') + $custom->getIza()['minutesIntervalForDisableCompany'] * 60;
                $pipeline[] = ['zAdd', IzaVariables::KEY_EVENTS_FORBIDDEN_REQUEST, $score, $eventResult['bandeira_id']];
            }
        } else {
            $removeCompanyInQueueForbiddenRequest = false;
            $eventResult['last_attempt'] = date('Y-m-d H:i:s');
            $pipeline[] = ['rPush', IzaVariables::KEY_EVENTS_CREATE_PERIOD, json_encode($eventResult)];
        }

        if ($removeCompanyInQueueForbiddenRequest) {
            $pipeline[] = ['zRem', IzaVariables::KEY_EVENTS_FORBIDDEN_REQUEST, $eventResult['bandeira_id']];
        }
    }
    $redis->pipelineCommands($pipeline);
}
$redis->decr(IzaVariables::KEY_MONITOR_CREATE_PERIOD);

Util::sendJson([
    'total_events' => $totalEvents,
    'total_events_success' => $totalEventsSuccess,
    'total_events_not_retry' => $totalEventsNotRetry,
]);
