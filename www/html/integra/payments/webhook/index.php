<?php

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

use src\geral\Custom;
use src\geral\RedisService;
use src\geral\RequestValidator;
use src\geral\Util;
use src\Payments\Handlers\WebhookHandler;
use src\geral\Enums\RequestConstants;

try {
    $custom = new Custom;
    $redisClient = new RedisService;
    $redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

    $validator = new RequestValidator(['type'], 'POST', RequestConstants::CURLOPT_POST_JSON_ENCODE);
    $validator->validateRequest();
    $requestData = $validator->getData();

    $handler = new WebhookHandler($redisClient);
    $handler->handler($requestData);

    Util::sendJson(['status' => true]);
} catch (\Throwable $th) {
    \MchLog::logAndInfo('log_payments', MchLogLevel::ERROR, [
        'message' => $th->getMessage(),
        'trace' => $th->getTraceAsString()
    ]);
    Util::sendJson(['status' => false], 400);
}