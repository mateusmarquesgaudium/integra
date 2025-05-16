<?php

require_once __DIR__ . '/../../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../../src/autoload.php';

use src\geral\Custom;
use src\geral\CustomException;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\RequestValidator;
use src\geral\Util;
use src\Opendelivery\Entities\EventFactory;
use src\Opendelivery\Handlers\ValidateWebhookHandler;

$custom = new Custom;
$redisService = new RedisService;
$redisService->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

try {
    $validateWebhookHandler = new ValidateWebhookHandler($redisService, $custom);
    $dataWebhook = $validateWebhookHandler->execute(getallheaders(), file_get_contents('php://input'));
    $provider = $dataWebhook['provider'];
    $merchant = $dataWebhook['merchant'];

    $requestValidator = new RequestValidator([
        'eventId', 'eventType', 'orderId', 'orderURL', 'createdAt'
    ], RequestConstants::POST, RequestConstants::CURLOPT_POST_JSON_ENCODE);
    $requestValidator->validateRequest();
    $eventData = $requestValidator->getData();

    try {
        $eventFactory = new EventFactory($redisService, $provider, $merchant);
        $eventBase = $eventFactory->create($eventData['eventType']);

        $eventData['provider'] = $provider;
        $eventData['merchantId'] = $merchant;

        $eventBase->execute($eventData);
    } catch (\InvalidArgumentException $iae) {
        MchLog::logAndInfo('log_opendelivery', MchLogLevel::ERROR, [
            'trace' => $iae->getTraceAsString(),
            'message' => $iae->getMessage(),
            'eventType' => $eventData['eventType'],
        ]);
    } catch (\Throwable $th) {
        throw $th;
    }

    Util::sendJson([], RequestConstants::HTTP_NO_CONTENT);
} catch (CustomException $ce) {
    MchLog::logAndInfo('log_opendelivery', MchLogLevel::ERROR, [
        'trace' => $ce->getTraceAsString(),
        'message' => $ce->getMessage(),
    ]);

    Util::sendJson(json_decode($ce->errorMessage, true), $ce->statusCode);
} catch (\Throwable $th) {
    MchLog::logAndInfo('log_opendelivery', MchLogLevel::ERROR, [
        'trace' => $th->getTraceAsString(),
        'message' => $th->getMessage(),
    ]);

    Util::sendJson([
        'title' => 'Service Unavailable',
        'message' => 'Service Unavailable',
    ], RequestConstants::HTTP_SERVICE_UNAVAILABLE);
}
