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

$maxInstances = 1;
$redis->checkMonitor(IzaVariables::KEY_MONITOR_DISABLED_CENTRAL, $maxInstances);
$redis->incrMonitor(IzaVariables::KEY_MONITOR_DISABLED_CENTRAL);

$totalEvents = 0;
$totalEventsSuccess = 0;

$centrals = $redis->sPop(izaVariables::KEY_EVENTS_DISABLED_CENTRAL, $redis->sCard(izaVariables::KEY_EVENTS_DISABLED_CENTRAL));
if (!empty($centrals)) {
    $totalEvents = count($centrals);

    $contentRequest = [];
    foreach ($centrals as $centralId) {
        $contentRequest[] = ['bandeira_id' => $centralId];
    }

    if (!empty($contentRequest)) {
        $request = new Request($configWebhook['url_disabled']);
        $response = $request->setHeaders([
                'Content-Type: application/json',
                'Authorization: Basic ' . $configWebhook['token_disabled'],
            ])
            ->setRequestMethod('POST')
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields($contentRequest)
            ->setTimeout(60000)
            ->execute();
        $content = json_decode($response?->content ?? '', true);
        $httpCode = $response?->http_code ?? null;

        if (empty($content) || !in_array($httpCode, [RequestConstants::HTTP_OK]) || empty($content['success']) || !$content['success']) {
            $totalEventsSuccess = 0;
            foreach ($contentRequest as $centralResult) {
                $redis->sAdd(IzaVariables::KEY_EVENTS_DISABLED_CENTRAL, $centralResult['bandeira_id']);
            }
        } else {
            foreach ($contentRequest as $centralResult) {
                $keys = $redis->keys(IzaVariables::KEY_EVENTS_ERROR_CREATE_PERSON . $centralResult['bandeira_id'] . ':*');
                $redis->del($keys);
            }
            $totalEventsSuccess++;
        }
    }
}

$redis->decr(IzaVariables::KEY_MONITOR_DISABLED_CENTRAL);

Util::sendJson([
    'total_events' => $totalEvents,
    'total_events_success' => $totalEventsSuccess,
]);