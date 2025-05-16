<?php

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

use src\Neemo\Enums\RedisSchema;
use src\geral\ {
    Custom,
    RedisService,
    Request,
    RequestValidator,
    Util,
};
use src\geral\Enums\RequestConstants;

try {
    $custom = new Custom;
    $redisClient = new RedisService;
    $redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

    $validator = new RequestValidator(['merchant_id'], 'DELETE', RequestConstants::CURLOPT_POST_JSON_ENCODE);
    $validator->validateRequest();
    $requestData = $validator->getData();

    $keyCacheCredential = str_replace('{merchant_id}', $requestData['merchant_id'], RedisSchema::KEY_CRENDENTIAL_MERCHANT);
    $redisClient->del($keyCacheCredential);

    Util::sendJson(['status' => true]);
} catch(\Throwable $e) {
    MchLog::logAndInfo('log_neemo', MchLogLevel::ERROR, [
        'trace' => $e->getTraceAsString(),
        'message' => $e->getMessage(),
    ]);
    Util::sendJson([
        'status' => false,
        'message' => $e->getMessage(),
    ]);
}