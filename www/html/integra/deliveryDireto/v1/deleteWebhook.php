<?php

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
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

    $validator = new RequestValidator(['merchant_id', 'username', 'password'], 'DELETE', RequestConstants::CURLOPT_POST_JSON_ENCODE);
    $validator->validateRequest();
    $requestData = $validator->getData();

    $oauth = new Oauth($redisClient, $requestData['merchant_id'], $requestData['username'], $requestData['password']);

    $webhook = new Webhook($oauth);
    if (!$webhook->deleteWebhook(WebhookType::ORDER_STATUS_CHANGED)) {
        Util::sendJson(['status' => false]);
    }

    $keyCacheCredential = str_replace('{merchant_id}', $requestData['merchant_id'], RedisSchema::KEY_CRENDENTIAL_MERCHANT);
    $redisClient->del($keyCacheCredential);

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