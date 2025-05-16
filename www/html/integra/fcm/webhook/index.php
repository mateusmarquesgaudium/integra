<?php

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

use src\Delivery\ProviderManagerRequest;
use src\Fcm\Handlers\WebhookHandler;
use src\geral\Custom;
use src\geral\RedisService;
use src\geral\RequestValidator;
use src\geral\Util;
use src\geral\Enums\RequestConstants;

try {
    $custom = new Custom;
    $redisClient = new RedisService;
    $redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

    $providerManagerRequest = new ProviderManagerRequest($custom);

    $validator = new RequestValidator(['webhookType', 'data', 'data.requests', 'data.path', 'data.accessToken'], 'POST', RequestConstants::CURLOPT_POST_JSON_ENCODE);
    $validator->validateRequest();
    $requestData = $validator->getData();

    $signature = $providerManagerRequest->createSignature($validator->getRawData());
    $providerManagerRequest->verifySignature($signature);

    $handler = new WebhookHandler($redisClient);
    $handler->handler($requestData);

    Util::sendJson(['status' => true]);
} catch (Throwable $th) {
    MchLog::log(MchLogLevel::ERROR, [
        'message' => $th->getMessage(),
        'trace' => $th->getTraceAsString()
    ]);
    Util::sendJson([
        'status' => false
    ], RequestConstants::HTTP_BAD_REQUEST);
}
