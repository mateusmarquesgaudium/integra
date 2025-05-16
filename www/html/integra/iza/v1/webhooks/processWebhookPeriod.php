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

$maxInstances = $custom->getIza()['maxInstancesWebhookPeriod'];
$redis->checkMonitor(IzaVariables::KEY_MONITOR_WEBHOOK_PERIOD, $maxInstances);
$redis->incrMonitor(IzaVariables::KEY_MONITOR_WEBHOOK_PERIOD);

$totalEvents = 0;
$totalEventsSuccess = 0;

$periods = $redis->sMembers(IzaVariables::KEY_WEBHOOK_CREATE_PERIOD);
if (!empty($periods)) {
    $redis->del(IzaVariables::KEY_WEBHOOK_CREATE_PERIOD);
    $totalEvents = count($periods);

    $contentRequest = [];
    foreach ($periods as $period) {
        $period = json_decode($period, true);

        if ($period && array_key_exists('periodo_id', $period)) {
            $contentRequest[] = [
                'periodo_id' => $period['periodo_id'],
                'solicitacao_id' => $period['solicitacao_id'],
                'identifier' => $period['identifier'] ?? null,
            ];
            $totalEventsSuccess++;
        } else {
            $redis->sAdd(IzaVariables::KEY_WEBHOOK_CREATE_PERIOD, json_encode($period));
        }
    }

    if (!empty($contentRequest)) {
        $request = new Request($configWebhook['url_period']);
        $response = $request->setHeaders([
                'Content-Type: application/json',
                'Authorization: Basic ' . $configWebhook['token_period'],
            ])
            ->setRequestMethod('POST')
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields($contentRequest)
            ->execute();
        $content = json_decode($response?->content ?? '', true);
        $httpCode = $response?->http_code ?? null;

        if (empty($content) || !in_array($httpCode, [RequestConstants::HTTP_OK]) || empty($content['success']) || !$content['success']) {
            $totalEventsSuccess = 0;
            foreach ($contentRequest as $periodResult) {
                $redis->sAdd(IzaVariables::KEY_WEBHOOK_CREATE_PERIOD, json_encode($periodResult));
            }
        }
    }
}

$redis->decr(IzaVariables::KEY_MONITOR_WEBHOOK_PERIOD);

Util::sendJson([
    'total_events' => $totalEvents,
    'total_events_success' => $totalEventsSuccess,
]);
