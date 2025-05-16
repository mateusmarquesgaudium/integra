<?php

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

use src\DeliveryMuch\Http\CreateIntegration;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use src\geral\RequestValidator;
use src\geral\Util;

try {
    $custom = new Custom;
    $redisService = new RedisService;
    $redisService->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

    $validator = new RequestValidator(['merchant_id', 'api_key', 'client_email', 'client_password'], 'PUT', RequestConstants::CURLOPT_POST_JSON_ENCODE);
    $validator->validateRequest();
    $requestData = $validator->getData();

    $createIntegration = new CreateIntegration(
        new Request($custom->getDeliveryMuch()['urlCreateIntegration']),
        $requestData['client_email'],
        $requestData['client_password'],
        $requestData['merchant_id'],
        $requestData['api_key']
    );

    $webhookId = $createIntegration->createIntegration();

    Util::sendJson(['status' => true, 'webhook_id' => $webhookId]);
} catch (\Throwable $th) {
    MchLog::logAndInfo('log_delivery_much', MchLogLevel::ERROR, [
        'message' => $th->getMessage(),
        'trace' => $th->getTraceAsString()
    ]);

    Util::sendJson([
        'status' => false,
        'message' => $th->getMessage(),
    ]);
}
