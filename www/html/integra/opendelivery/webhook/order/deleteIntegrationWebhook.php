<?php

require_once __DIR__ . '/../../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../../src/autoload.php';

use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\RequestValidator;
use src\geral\Util;
use src\Opendelivery\Enums\RedisSchema;

try {
    $custom = new Custom;
    $redisService = new RedisService;
    $redisService->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

    $requestValidator = new RequestValidator(['merchant_id', 'empresa_id', 'provider'], 'DELETE', RequestConstants::CURLOPT_POST_JSON_ENCODE);
    $requestValidator->validateRequest();
    $requestData = $requestValidator->getData();

    $keyCacheCredential = str_replace('{provider}', $requestData['provider'], RedisSchema::KEY_MERCHANTS_PROVIDER);
    $redisService->sRem($keyCacheCredential, $requestData['merchant_id']);

    $key = str_replace(['{provider}', '{enterpriseId}'], [$requestData['provider'], $requestData['empresa_id']], RedisSchema::KEY_ACCESS_TOKEN_PROVIDER);
    $redisService->del($key);

    $key = str_replace(['{provider}', '{merchantId}'], [$requestData['provider'], $requestData['merchant_id']], RedisSchema::KEY_ACCESS_TOKEN_PROVIDER_MERCHANT);
    $redisService->del($key);

    Util::sendJson(['status' => true]);
} catch (\Throwable $th) {
    MchLog::logAndInfo('log_opendelivery', MchLogLevel::ERROR, [
        'trace' => $th->getTraceAsString(),
        'message' => $th->getMessage(),
    ]);

    Util::sendJson([
        'status' => false,
        'message' => $th->getMessage(),
    ]);
}
