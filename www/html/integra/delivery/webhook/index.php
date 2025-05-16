<?php
require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

use src\Delivery\Enums\Provider;
use src\Delivery\Handlers\WebhookHandler;
use src\Delivery\ProviderManagerRequest;
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

    $provider = $_GET['provider'] ?? null;
    $providerManagerRequest = new ProviderManagerRequest($custom);

    $fieldsToValidate = [];
    $responseHttpCode = RequestConstants::HTTP_OK;
    if ($providerManagerRequest->provider === Provider::IFOOD) {
        $fieldsToValidate = ['fullCode'];
        $responseHttpCode = RequestConstants::HTTP_CREATED;
    } elseif ($providerManagerRequest->provider === Provider::DELIVERY_DIRETO) {
        $fieldsToValidate = ['ordersId', 'orderStatus'];
    } elseif ($providerManagerRequest->provider === Provider::MACHINE) {
        $fieldsToValidate = ['webhookType'];
    } elseif ($providerManagerRequest->provider === Provider::ANOTAAI) {
        $fieldsToValidate = ['id', 'merchant'];
    } elseif ($providerManagerRequest->provider === Provider::NEEMO) {
        $fieldsToValidate = ['order_id', 'account_access_token'];
    } elseif ($providerManagerRequest->provider === Provider::AIQFOME) {
        $fieldsToValidate = ['event', 'store_id', 'data', 'data.order_id'];
    }

    $validator = new RequestValidator($fieldsToValidate, 'POST', RequestConstants::CURLOPT_POST_JSON_ENCODE);
    $validator->validateRequest();

    if ($providerManagerRequest->hasSignatureRequiredValidate()) {
        $signature = $providerManagerRequest->createSignature($validator->getRawData());
        $providerManagerRequest->verifySignature($signature);
    }

    $webhookHandler = new WebhookHandler($providerManagerRequest, $redisClient);
    $webhookHandler->handleWebhook($validator->getData());

    Util::sendJson([
        'status' => true
    ], $responseHttpCode);
} catch(\Throwable $t) {
    MchLog::logAndInfo('log_webhook', MchLogLevel::ERROR, [
        'trace' => $t->getTraceAsString(),
        'message' => $t->getMessage(),
        'headers' => getallheaders(),
        'body' => json_decode(file_get_contents('php://input'), true) ?? [],
    ]);
    Util::sendJson([
        'status' => false
    ], 500);
}