<?php
require_once __DIR__ . '/../../../../../src/autoload.php';

use src\geral\ {
    Custom,
    RedisService,
    Request,
    RequestMulti
};
use src\geral\Enums\RequestConstants;
use src\iza\IzaVariables;

$custom = new Custom;

$redis = new RedisService;
$redis->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxInstances = $custom->getIza()['maxInstancesSearchContract'];
$redis->checkMonitor(IzaVariables::KEY_MONITOR_SEARCH_CONTRACT, $maxInstances);
$redis->incrMonitor(IzaVariables::KEY_MONITOR_SEARCH_CONTRACT);

$maxEventsAtATime = $custom->getIza()['maxEventsAtATime'];
$events = $redis->lRange(IzaVariables::KEY_EVENTS_SEARCH_CONTRACT, 0, $maxEventsAtATime - 1);
if (!empty($events) && is_array($events)) {
    $redis->ltrim(IzaVariables::KEY_EVENTS_SEARCH_CONTRACT, count($events), -1);

    $requests = new RequestMulti;

    $urlIza = $custom->getIza()['url'];
    $maxAttemptsRequest = $custom->getIza()['maxAttemptsRequest'];

    // Inicializa os requests
    foreach ($events as &$event) {
        $event = json_decode($event, true);
        $event['index'] = $event['taxista_id'] . $event['solicitacao_id'];
        $event['attempts'] = ($event['attempts'] ?? 0) + 1;

        $request = new Request($urlIza . '/persons?doc=' . $event['doc']);
        $request
            ->setHeaders($headers = [
                'Content-Type: application/json',
                'Authorization: Basic ' . $event['chave_integracao'],
            ])
            ->setRequestMethod('GET');
        $requests->setRequest($event['index'], $request);
    }
    
    $response = $requests->execute();

    // Percore os events buscando o request retornado pelo IZA
    foreach ($events as $eventResult) {
        $request = $response[$eventResult['index']] ?? [];
        $content = json_decode($request?->content ?? '', true);
        $content = $content[0] ?? $content;
        $httpCode = $request?->http_code ?? null;


        if (!empty($request) && !empty($content['id']) && in_array($httpCode, [RequestConstants::HTTP_OK, RequestConstants::HTTP_CREATED, RequestConstants::HTTP_ACCEPTED])) {
            if (!empty($content['contracts']) && is_array($content['contracts'])) {
                $contractId = null;
                $eventResult['content'] = $content;
                foreach ($content['contracts'] as $contract) {
                    if (!empty($contract['id']) && !empty($contract['status']) && in_array($contract['status'], ['active', 'opened'])) {
                        $contractId = $contract['id'];
                        break;
                    }
                }
                if (!empty($contractId)) {
                    $webhook = [
                        'bandeira_id' => $eventResult['bandeira_id'],
                        'taxista_id' => $eventResult['taxista_id'],
                        'contrato_id' => $contractId,
                    ];
                    $redis->sAdd(IzaVariables::KEY_WEBHOOK_CREATE_PERSON, json_encode($webhook));
                } else {
                    $redis->lPush(IzaVariables::KEY_EVENTS_ERROR_SEARCH_CONTRACT, json_encode($eventResult));    
                }
            } else {
                $redis->lPush(IzaVariables::KEY_EVENTS_ERROR_SEARCH_CONTRACT, json_encode($eventResult));
            }
        } elseif ($eventResult['attempts'] >= $maxAttemptsRequest) {
            $eventResult['error'] = $content; 
            $eventResult['httpCode'] = $httpCode;
            $redis->lPush(IzaVariables::KEY_EVENTS_ERROR_SEARCH_CONTRACT, json_encode($eventResult));
        } else {
            $redis->rPush(IzaVariables::KEY_EVENTS_SEARCH_CONTRACT, json_encode($eventResult));
        }
    }
}
$redis->decr(IzaVariables::KEY_MONITOR_SEARCH_CONTRACT);
