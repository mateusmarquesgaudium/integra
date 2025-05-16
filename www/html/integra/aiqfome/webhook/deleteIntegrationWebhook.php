<?php

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

use src\Aiqfome\Handlers\CheckMerchantInProviderHandler;
use src\Aiqfome\Http\AuthenticateToken;
use src\Aiqfome\Http\DeleteIntegration;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use src\geral\RequestValidator;
use src\geral\Util;

try {
    $custom = new Custom;
    $redisClient = new RedisService;
    $redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

    $validator = new RequestValidator(['merchant_id', 'webhooks'], 'DELETE', RequestConstants::CURLOPT_POST_JSON_ENCODE);
    $validator->validateRequest();
    $requestData = $validator->getData();

    $request = new Request($custom->getParams('aiqfome')['url'] . "/store/{$requestData['merchant_id']}/info");
    $checkMerchantInProvider = new CheckMerchantInProviderHandler($request, $redisClient);

    $request = new Request($custom->getParams('aiqfome')['url'] . '/auth/token');
    $authenticateHandler = new AuthenticateToken($request, $custom, $redisClient);
    $authenticateHandler->handle($requestData['client_username'], $requestData['client_password'], $requestData['empresa_id']);

    $deleteIntegration = new DeleteIntegration($custom, $redisClient, $checkMerchantInProvider);
    $deleteIntegration->execute($requestData['merchant_id'], $requestData['webhooks']);

    Util::sendJson([
        'status' => true,
    ]);
} catch (\Throwable $th) {
    MchLog::info('log_aiqfome', [
        'message' => $th->getMessage(),
        'trace' => $th->getTraceAsString()
    ]);
    Util::sendJson([
        'status' => false,
        'message' => $th->getMessage(),
    ]);
}