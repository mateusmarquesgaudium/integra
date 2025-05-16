<?php

require_once __DIR__ . '/../../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../../src/autoload.php';

use src\geral\Custom;
use src\geral\CustomException;
use src\geral\RedisService;
use src\geral\Util;
use src\Opendelivery\Handlers\AuthenticateHandler;
use src\Opendelivery\Handlers\LogisticsHandler;

try {
    $custom = new Custom;
    $redisClient = new RedisService;
    $redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

    $authenticateHandler = new AuthenticateHandler($_SERVER['HTTP_AUTHORIZATION'] ?? '', $custom, $redisClient);
    $authenticateHandler->validarToken();

    $logisticsHandler = new LogisticsHandler($redisClient, $custom, $authenticateHandler->getProvider());
    Util::sendJson($logisticsHandler->getDelivery());
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
        'title' => $ce->errorMessage,
        'status' => $ce->statusCode
    ], $ce->statusCode);
} catch (\Exception $e) {
    MchLog::logAndInfo('log_opendelivery', MchLogLevel::ERROR, [
        'message' => $e->getMessage(),
        'rawData' => file_get_contents('php://input') ?: null,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    Util::sendJson([
        'title' => $e->getMessage(),
        'status' => 400
    ], 400);
} catch (\Throwable $th) {
    MchLog::logAndInfo('log_opendelivery', MchLogLevel::ERROR, [
        'message' => $th->getMessage(),
        'rawData' => file_get_contents('php://input') ?: null,
        'file' => $th->getFile(),
        'line' => $th->getLine(),
        'trace' => $th->getTraceAsString()
    ]);
    Util::sendJson([
        'title' => 'The service is temporarily unavailable. The disruption may be due to scheduled maintenance or be outside of the normal operating hours.',
        'status' => 503
    ], 503);
}