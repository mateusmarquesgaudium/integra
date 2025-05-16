<?php
require_once __DIR__ . '/../../../../../src/autoload.php';

use src\geral\{
    Custom,
    RedisService,
    Request,
    Util,
};
use src\geral\Enums\RequestConstants;
use src\iza\IzaVariables;

$custom = new Custom;
$configWebhook = $custom->getIza()['webhook'];

$redis = new RedisService;
$redis->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxInstances = $custom->getIza()['maxInstancesWebhookPerson'];
$redis->checkMonitor(IzaVariables::KEY_MONITOR_WEBHOOK_PERSON, $maxInstances);
$redis->incrMonitor(IzaVariables::KEY_MONITOR_WEBHOOK_PERSON);

$totalEvents = 0;
$totalEventsSuccess = 0;

$persons = $redis->sMembers(IzaVariables::KEY_WEBHOOK_CREATE_PERSON);
if (!empty($persons)) {
    $redis->del(IzaVariables::KEY_WEBHOOK_CREATE_PERSON);
    $totalEvents = count($persons);

    $contentRequest = [];
    foreach ($persons as $person) {
        $person = json_decode($person, true);

        if ($person && array_key_exists('contrato_id', $person)) {
            $contentRequest[] = [
                'bandeira_id' => $person['bandeira_id'],
                'taxista_id' => $person['taxista_id'],
                'contrato_id' => $person['contrato_id'],
            ];
            $totalEventsSuccess++;
        } else {
            $redis->sAdd(IzaVariables::KEY_WEBHOOK_CREATE_PERSON, json_encode($person));
        }
    }

    if (!empty($contentRequest)) {
        $request = new Request($configWebhook['url_person']);
        $response = $request->setHeaders([
                'Content-Type: application/json',
                'Authorization: Basic ' . $configWebhook['token_person'],
            ])
            ->setRequestMethod('POST')
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields($contentRequest)
            ->execute();
        $content = json_decode($response?->content ?? '', true);
        $httpCode = $response?->http_code ?? null;

        if (empty($content) || !in_array($httpCode, [RequestConstants::HTTP_OK]) || empty($content['success']) || !$content['success']) {
            $totalEventsSuccess = 0;
            foreach ($contentRequest as $personResult) {
                $redis->sAdd(IzaVariables::KEY_WEBHOOK_CREATE_PERSON, json_encode($personResult));
            }
        }
    }
}

$redis->decr(IzaVariables::KEY_MONITOR_WEBHOOK_PERSON);

Util::sendJson([
    'total_events' => $totalEvents,
    'total_events_success' => $totalEventsSuccess,
]);