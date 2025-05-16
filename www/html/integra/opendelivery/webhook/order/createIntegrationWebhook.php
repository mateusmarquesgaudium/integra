<?php

require_once __DIR__ . '/../../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../../src/autoload.php';

use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use src\geral\RequestValidator;
use src\geral\Util;
use src\Opendelivery\Enums\RedisSchema;
use src\Opendelivery\Handlers\GetAccessTokenHandler;

try {
    $custom = new Custom;
    $redisService = new RedisService;
    $redisService->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

    $requestValidator = new RequestValidator(['merchant_id', 'client_secret', 'client_id', 'provider'], 'PUT', RequestConstants::CURLOPT_POST_JSON_ENCODE);
    $requestValidator->validateRequest();
    $requestData = $requestValidator->getData();

    $getAccessTokenHandler = new GetAccessTokenHandler(
        new Request($custom->getOpenDelivery()['provider'][$requestData['provider']]['url'] . '/oauth/token'),
        $redisService,
        $requestData['provider'],
        $requestData['client_id'],
        $requestData['client_secret'],
        $requestData['merchant_id'],
    );

    $getAccessTokenHandler->execute();

    $keyCacheCredential = str_replace('{provider}', $requestData['provider'], RedisSchema::KEY_MERCHANTS_PROVIDER);
    $redisService->sAdd($keyCacheCredential, $requestData['merchant_id']);

    Util::sendJson(['status' => true]);
} catch (\Throwable $th) {
    MchLog::logAndInfo('log_opendelivery', MchLogLevel::ERROR, [
        'trace' => $th->getTraceAsString(),
        'message' => $th->getMessage(),
    ]);

    Util::sendJson([
        'status' => false,
        'trace' => $th->getTraceAsString(),
        'message' => $th->getMessage(),
    ]);
}
