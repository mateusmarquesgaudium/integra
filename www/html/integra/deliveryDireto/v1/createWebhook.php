<?php

require_once __DIR__ . '/../../../../src/autoload.php';

use src\DeliveryDireto\Enums\RedisSchema;
use src\DeliveryDireto\Enums\WebhookType;
use src\DeliveryDireto\Http\ {
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

    $validator = new RequestValidator(['merchant_id', 'username', 'password'], 'PUT', RequestConstants::CURLOPT_POST_JSON_ENCODE);
    $validator->validateRequest();
    $requestData = $validator->getData();

    $oauth = new Oauth($redisClient, $requestData['merchant_id'], $requestData['username'], $requestData['password']);

    $webhook = new Webhook($oauth);
    if (!$webhook->createWebhook(WebhookType::ORDER_STATUS_CHANGED, $custom->getParams('delivery')['urlWebhookEvents'])) {
        Util::sendJson(['status' => false]);
    }

    $keyCacheCredential = str_replace('{merchant_id}', $requestData['merchant_id'], RedisSchema::KEY_CRENDENTIAL_MERCHANT);
    $redisClient->set($keyCacheCredential, json_encode([
        'username' => $requestData['username'],
        'password' => $requestData['password'],
    ]));

    Util::sendJson(['status' => true]);
} catch(\Throwable $e) {
    MchLog::logAndInfo('log_delivery_direto', MchLogLevel::ERROR, [
        'trace' => $e->getTraceAsString(),
        'message' => $e->getMessage(),
    ]);
    Util::sendJson([
        'status' => false,
        'message' => $e->getMessage(),
    ]);
}