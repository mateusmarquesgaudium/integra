<?php

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use src\geral\RequestValidator;
use src\geral\Util;
use src\Opendelivery\Entities\OrderCache;

try {
    $custom = new Custom;
    $redisClient = new RedisService;
    $redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

    $validator = new RequestValidator(['orders'], 'POST', RequestConstants::CURLOPT_POST_JSON_ENCODE);
    $validator->validateRequest();
    $requestData = $validator->getData();

    $pipeline = [];
    foreach ($requestData['orders'] as $orderData) {
        $orderCache = new OrderCache($redisClient, $orderData['provider']);
        $orderDetail = $orderCache->getOrderCache($orderData['orderId']);

        $keyUnattended = $orderDetail['keyUnattended'];
        if (empty($keyUnattended)) {
            continue;
        }

        $pipeline[] = ['unlink', $keyUnattended];
    }

    $redisClient->pipelineCommands($pipeline);
    Util::sendJson(['status' => true]);
} catch (\Throwable $th) {
    echo $th->getMessage();
    MchLog::logAndInfo('log_opendelivery', MchLogLevel::ERROR, [
        'trace' => $th->getTraceAsString(),
        'message' => $th->getMessage(),
    ]);
    Util::sendJson(['status' => false], 400);
}