<?php

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

use src\AnotaAi\Enums\RedisSchema;
use src\AnotaAi\Http\ {
    Oauth,
    Webhook,
};
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

    $validator = new RequestValidator(['merchant_id', 'token'], 'PUT', RequestConstants::CURLOPT_POST_JSON_ENCODE);
    $validator->validateRequest();
    $requestData = $validator->getData();

    $oauth = new Oauth($redisClient, $requestData['merchant_id'], $requestData['token']);

    $createWebhook = new Webhook($oauth);
    $tokenAnotaAi = $createWebhook->createWebhook($requestData['token'], $custom->getParams('delivery')['urlWebhookEvents']);
    if (!$tokenAnotaAi) {
        Util::sendJson(['status' => false]);
    }

    $keyCacheCredential = str_replace('{merchant_id}', $requestData['merchant_id'], RedisSchema::KEY_CRENDENTIAL_MERCHANT);
    $redisClient->set($keyCacheCredential, json_encode([
        'token' => $tokenAnotaAi,
    ]));

    Util::sendJson([
        'status' => true,
        'token' => $tokenAnotaAi,
    ]);
} catch(\Throwable $e) {
    MchLog::logAndInfo('log_anotaai', MchLogLevel::ERROR, [
        'trace' => $e->getTraceAsString(),
        'message' => $e->getMessage(),
    ]);
    Util::sendJson([
        'status' => false,
        'message' => $e->getMessage(),
    ]);
}
