<?php
require_once __DIR__ . '/../../../../../src/autoload.php';

use src\geral\ {
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
$redis->checkMonitor(IzaVariables::KEY_MONITOR_WEBHOOK_NOTIFICATION_PENDING, $maxInstances);
$redis->incrMonitor(IzaVariables::KEY_MONITOR_WEBHOOK_NOTIFICATION_PENDING);

$totalEvents = 0;
$totalEventsSuccess = 0;

$bandeiras = $redis->sMembers(IzaVariables::KEY_WEBHOOK_ERROR_CREATE_PERSON);
if (empty($bandeiras)) {
    $redis->decr(IzaVariables::KEY_MONITOR_WEBHOOK_NOTIFICATION_PENDING);

    Util::sendJson([
        'total_events' => $totalEvents,
        'total_events_success' => $totalEventsSuccess,
    ]);
}

$redis->del(IzaVariables::KEY_WEBHOOK_ERROR_CREATE_PERSON);
$contentRequest = [];
foreach ($bandeiras as $bandeiraId) {
    $keys = $redis->keys(IzaVariables::KEY_EVENTS_ERROR_CREATE_PERSON . $bandeiraId . ':*');
    $contentRequest[$bandeiraId] = 0;

    if (empty($keys)) {
        continue;
    }

    $totalEvents += count($keys);
    foreach ($keys as $key) {
        $person = $redis->get($key);
        $person = json_decode($person, true);
        
        if (!empty($person['taxista_id']) && !empty($person['erros'])) {
            $contentRequest[$bandeiraId]++;
            $totalEventsSuccess++;
        }
    }
}

if (!empty($contentRequest)) {
    $request = new Request($configWebhook['url_notification']);
    $response = $request->setHeaders([
        'Content-Type: application/json',
        'Authorization: Basic ' . $configWebhook['token_notification'],
    ])
    ->setRequestMethod('POST')
    ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
    ->setPostFields($contentRequest)
    ->execute();

    $content = json_decode($response?->content ?? '', true);
    $httpCode = $response?->http_code ?? null;

    if (empty($content) || !in_array($httpCode, [RequestConstants::HTTP_OK]) || empty($content['success']) || !$content['success']) {
        $totalEventsSuccess = 0;
        foreach (array_keys($contentRequest) as $bandeiraId) {
            $redis->sAdd(IzaVariables::KEY_WEBHOOK_ERROR_CREATE_PERSON, $bandeiraId);
        }
    }
}

$redis->decr(IzaVariables::KEY_MONITOR_WEBHOOK_NOTIFICATION_PENDING);

Util::sendJson([
    'total_events' => $totalEvents,
    'total_events_success' => $totalEventsSuccess,
]);