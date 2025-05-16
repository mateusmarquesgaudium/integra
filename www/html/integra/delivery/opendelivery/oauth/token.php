<?php

require_once __DIR__ . '/../../../../../src/autoload.php';
require_once __DIR__ . '/../../../../../mchlogtoolkit/MchLog.php';

use src\geral\Custom;
use src\geral\CustomException;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\RequestValidator;
use src\geral\Util;
use src\Opendelivery\Enums\RedisSchema;
use src\Opendelivery\Enums\Variables;
use src\Opendelivery\Handlers\OauthHandler;

try {
    $custom = new Custom;
    $validator = new RequestValidator([], 'POST', RequestConstants::CURLOPT_POST_NORMAL_DATA);
    $validator->validateRequest();
    $requestData = $validator->getData();

    if (empty($requestData['grant_type'])) {
        throw new CustomException(
            json_encode([
                'type' => Variables::TYPE_INVALID,
                'message' => 'Missing required parameter \'grant_type\''
            ]),
            400
        );
    }

    if ($requestData['grant_type'] != Variables::GRANT_TYPE) {
        throw new CustomException(
            json_encode([
                'type' => Variables::TYPE_INVALID,
                'message' => 'Invalid grant_type format'
            ]),
            400
        );
    }

    $clientId = $requestData['client_id'] ?? null;
    $clientSecret = $requestData['client_secret'] ?? null;

    $redisClient = new RedisService;
    $redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

    $oauthHandler = new OauthHandler($redisClient, $clientId, $clientSecret);
    $token = $oauthHandler->authenticateUser();

    Util::sendJson([
        'access_token' => $token,
        'token_type' => 'bearer',
        'expires_in' => RedisSchema::TTL_AUTHENTICATE_ORDER_SERVICE
    ]);
} catch (CustomException $ce) {
    MchLog::logAndInfo('log_opendelivery', MchLogLevel::INFO, [
        'message' => $ce->errorMessage,
        'statusCode' => $ce->statusCode,
        'rawData' => file_get_contents('php://input') ?: null,
        'file' => $ce->getFile(),
        'line' => $ce->getLine(),
        'trace' => $ce->getTraceAsString()
    ]);
    Util::sendJson([
        'result' => 'failure',
        'errors' => json_decode($ce->errorMessage),
        'status' => $ce->statusCode,
    ], $ce->statusCode);
} catch (\Throwable $th) {
    MchLog::logAndInfo('log_opendelivery', MchLogLevel::ERROR, [
        'message' => $th->getMessage(),
        'rawData' => file_get_contents('php://input') ?: null,
        'file' => $th->getFile(),
        'line' => $th->getLine(),
        'trace' => $th->getTraceAsString()
    ]);
    Util::sendJson([
        'title' => 'Internal Server Error',
        'status' => 500
    ], 503);
}
