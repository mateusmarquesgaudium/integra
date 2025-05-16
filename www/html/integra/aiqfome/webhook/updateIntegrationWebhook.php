<?php

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

use src\Aiqfome\Http\AuthenticateToken;
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

    $validator = new RequestValidator(['client_username', 'empresa_id', 'client_password'], 'PUT', RequestConstants::CURLOPT_POST_JSON_ENCODE);
    $validator->validateRequest();
    $requestData = $validator->getData();

    $request = new Request($custom->getParams('aiqfome')['url'] . '/auth/token');
    $authenticateHandler = new AuthenticateToken($request, $custom, $redisService);
    $authenticateHandler->handle($requestData['client_username'], $requestData['client_password'], $requestData['empresa_id']);

    Util::sendJson([
        'status' => true,
        'message' => 'Webhook atualizado com sucesso.'
    ]);
} catch (\Throwable $th) {
    MchLog::logAndInfo('log_aiqfome', MchLogLevel::ERROR, [
        'message' => $th->getMessage(),
        'trace' => $th->getTraceAsString()
    ]);

    Util::sendJson([
        'status' => false,
        'message' => $th->getMessage()
    ], 500);
}