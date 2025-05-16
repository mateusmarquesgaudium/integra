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

$maxInstances = $custom->getIza()['maxInstancesCreatePerson'];
$redis->checkMonitor(IzaVariables::KEY_MONITOR_CREATE_PERSON, $maxInstances);
$redis->incrMonitor(IzaVariables::KEY_MONITOR_CREATE_PERSON);

$maxEventsAtATime = $custom->getIza()['maxEventsAtATimePerson'];
$events = $redis->lRange(IzaVariables::KEY_EVENTS_CREATE_PERSON, 0, $maxEventsAtATime - 1);
if (!empty($events) && is_array($events)) {
    $redis->ltrim(IzaVariables::KEY_EVENTS_CREATE_PERSON, count($events), -1);

    $requestsPerson = new RequestMulti;
    $requestsContract = new RequestMulti;

    $urlIza = $custom->getIza()['url'];
    $maxAttemptsRequest = $custom->getIza()['maxAttemptsRequest'];

    // Inicializa os requests
    foreach ($events as &$event) {
        $event = json_decode($event, true);
        $event['index'] = $event['taxista_id'] . $event['solicitacao_id'];
        $event['attempts'] = ($event['attempts'] ?? 0) + 1;

        $requestPerson = new Request($urlIza . '/persons');
        $requestPerson
            ->setHeaders($headers = [
                'Content-Type: application/json',
                'Authorization: Basic ' . $event['chave_integracao'],
            ])
            ->setRequestMethod('POST')
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields([
                'doc' => $event['doc'],
                'name' => $event['name'],
                'birthed_at' => $event['birthed_at'],
                'email' => $event['email'],
                'main_cell_phone' => $event['main_cell_phone'],
            ]);
        $requestsPerson->setRequest($event['index'], $requestPerson);
    }

    // Executa os requests
    $response = $requestsPerson->execute();
    unset($requestsPerson);

    // Percore os events buscando o request retornado pelo IZA
    foreach ($events as $key => $eventPerson) {
        $request = $response[$eventPerson['index']] ?? [];
        $content = json_decode($request?->content ?? '', true);
        $httpCode = $request?->http_code ?? null;

        if (!empty($request) && !empty($content['id'])
            && in_array($httpCode, [RequestConstants::HTTP_OK, RequestConstants::HTTP_CREATED, RequestConstants::HTTP_ACCEPTED])
            || isset($content['errors'], $content['errors']['detail']) && $content['errors']['detail'] == 'Conflict'
        ) {
            $requestContract = new Request($urlIza . '/contracts');
            $requestContract
                ->setHeaders($headers = [
                    'Content-Type: application/json',
                    'Authorization: Basic ' . $eventPerson['chave_integracao'],
                ])
                ->setRequestMethod('POST')
                ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
                ->setPostFields([
                    'doc' => $eventPerson['doc'],
                ]);
            $requestsContract->setRequest($eventPerson['index'], $requestContract);
        } elseif ($eventPerson['attempts'] >= $maxAttemptsRequest) {
            $driverCache = $redis->get(IzaVariables::KEY_EVENTS_ERROR_CREATE_PERSON . $eventPerson['bandeira_id'] . ':' . $eventPerson['taxista_id']);
            $driverCache = json_decode($driverCache, true);
            $eventPerson['erros'] = [];
            if (!empty($driverCache['erros'])) {
                $eventPerson['erros'] = $driverCache['erros'];
            }
            $eventPerson['origem'] = IzaVariables::IZA_ERRORS_ORIGIN;
            if (!empty($content['errors']) && is_array($content['errors'])) {
                $keyErros = array_keys($content['errors']);
                if (in_array(IzaVariables::EMAIL_CODE_ERROR, $keyErros) && !in_array(IzaVariables::EMAIL_CODE_ERROR, $eventPerson['erros'])) {
                    $eventPerson['erros'][] = IzaVariables::EMAIL_CODE_ERROR;
                }
                if (in_array(IzaVariables::PHONE_CODE_ERROR, $keyErros) && !in_array(IzaVariables::PHONE_CODE_ERROR, $eventPerson['erros'])) {
                    $eventPerson['erros'][] = IzaVariables::PHONE_CODE_ERROR;
                }
                if (in_array(IzaVariables::DETAILS_CODE_ERROR, $keyErros) && !in_array($content['errors'][IzaVariables::DETAILS_CODE_ERROR], $eventPerson['erros']) && $content['errors'][IzaVariables::DETAILS_CODE_ERROR] != IzaVariables::INVALID_CODE_ERROR) {
                    $eventPerson['erros'][] = $content['errors'][IzaVariables::DETAILS_CODE_ERROR];
                }
            }
            if (empty($eventPerson['erros'])) {
                $eventPerson['fatal_error'] = $content;
            }

            $eventPerson['httpCode'] = $httpCode;
            $redis->sAdd(IzaVariables::KEY_WEBHOOK_ERROR_CREATE_PERSON, $eventPerson['bandeira_id']);
            $redis->set(IzaVariables::KEY_EVENTS_ERROR_CREATE_PERSON . $eventPerson['bandeira_id'] . ':' . $eventPerson['taxista_id'], json_encode($eventPerson));
            unset($events[$key]);
        } else {
            $redis->rPush(IzaVariables::KEY_EVENTS_CREATE_PERSON, json_encode($eventPerson));
            unset($events[$key]);
        }
    }

    unset($response);
    if ($requestsContract->haveRequests()) {
        // Executa os requests
        $response = $requestsContract->execute();
        unset($requestsContract);

        // Percore os events buscando o request retornado pelo IZA
        foreach ($events as $eventContract) {
            $request = $response[$eventContract['index']] ?? [];
            $content = json_decode($request?->content ?? '', true);
            $httpCode = $request?->http_code ?? null;

            $hasContract = (isset($content['errors'], $content['errors']['details']) && $content['errors']['details'] == 'already_has_contract');

            if (!empty($request) && !empty($content['id']) && in_array($httpCode, [RequestConstants::HTTP_OK, RequestConstants::HTTP_CREATED, RequestConstants::HTTP_ACCEPTED]) || $hasContract) {
                unset($eventContract['name'], $eventContract['birthed_at'], $eventContract['email'], $eventContract['main_cell_phone'], $eventContract['attempts']);

                if (!empty($content['id'])) {
                    $webhook = [
                        'bandeira_id' => $eventContract['bandeira_id'],
                        'taxista_id' => $eventContract['taxista_id'],
                        'contrato_id' => $content['id'],
                    ];
                    $redis->sAdd(IzaVariables::KEY_WEBHOOK_CREATE_PERSON, json_encode($webhook));
                }

                $contract = $eventContract['bandeira_contrato'] ?? IzaVariables::CONTRACT_PER_REQUEST;
                if ($contract === IzaVariables::CONTRACT_PER_STOP) {
                    $createKey = IzaVariables::KEY_EVENTS_CREATE_STOP_PERIOD;
                } else {
                    $createKey = IzaVariables::KEY_EVENTS_CREATE_PERIOD;
                }
                $redis->rPush($createKey, json_encode($eventContract));

                if ($hasContract) {
                    $eventContract['attempts'] = 0;
                    $redis->rPush(IzaVariables::KEY_EVENTS_SEARCH_CONTRACT, json_encode($eventContract));
                }
            } elseif ($eventContract['attempts'] >= $maxAttemptsRequest) {
                $eventContract['error'] = $content;
                $eventContract['httpCode'] = $httpCode;
                $redis->lPush(IzaVariables::KEY_EVENTS_ERROR_CREATE_CONTRACT, json_encode($eventContract));
            } else {
                $redis->rPush(IzaVariables::KEY_EVENTS_CREATE_PERSON, json_encode($eventContract));
            }
        }
    }
}
$redis->decr(IzaVariables::KEY_MONITOR_CREATE_PERSON);
