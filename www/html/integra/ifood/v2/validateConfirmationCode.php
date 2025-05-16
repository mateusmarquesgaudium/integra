<?php
require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';
require_once __DIR__ . '/../../../../src/ifood/Service.php';
require_once __DIR__ . '/../../../../src/ifood/IfoodServiceV2.php';

use src\geral\{
    RedisService,
    Util,
};
use src\geral\Enums\RequestConstants;
use src\ifood\Enums\OauthMode;
use src\ifood\Enums\RedisSchema;
use src\ifood\Enums\DeliveryConfirmationError;

$redis = new RedisService;
$redis->connectionRedis($custom['redis']['hostname'], $custom['redis']['port']);

$result = ['success' => false];
$postForm = filter_input_array(INPUT_POST, FILTER_DEFAULT);

if (empty($postForm)) {
    $result['erros'] = 'Falha no envio dos parametros';
    Util::sendJson($result);
}

if (empty($postForm['orderId'])) {
    $erros[] = 'Id da solicitação inválido';
}

if (empty($postForm['code'])) {
    $erros[] = 'Código de confirmação do pedido não enviado';
}

if (!empty($erros)) {
    $result['erros'] = $erros;
    Util::sendJson($result);
}

$useWebhook = !empty($redis->get(RedisSchema::KEY_ENABLE_WEBHOOK));
$oauthMode = !$useWebhook ? OauthMode::POLLING : OauthMode::WEBHOOK;

$oauth = createOauth($redis, $oauthMode);
if (empty($oauth)) {
    Util::sendJson([
        'success' => false,
        'message' => 'Não foi possível criar um token de acesso.'
    ]);
}

$orderId = $postForm['orderId'];
$code = $postForm['code'];

$keyOrderCodeConfirmation = str_replace('{orderId}', $orderId, $redisOrderCodeConfirmation);

$codeInCache = $redis->get($keyOrderCodeConfirmation);
if (!empty($codeInCache)) {
    $result['success'] = true;
    Util::sendJson($result);
}

$keyOrderCodeInvalid = str_replace('{order_id}', $orderId, RedisSchema::KEY_ORDER_CODE_INVALID);
$codeInvalid = $redis->sismember($keyOrderCodeInvalid, $code);
if (!empty($codeInvalid)) {
    $result['content'] = ['message' => 'Código de confirmação inválido'];
    Util::sendJson($result);
}

$response = requestConfirmDelivery($oauth, $orderId, $code, $useWebhook);
$responseCode = $response?->http_code;
$errorCode = json_decode($response?->content)->code ?? null;

if ($responseCode == RequestConstants::HTTP_OK || ($responseCode == RequestConstants::HTTP_UNPROCESSABLE_ENTITY && in_array($errorCode, [DeliveryConfirmationError::ORDER_ALREADY_CONFIRMED, DeliveryConfirmationError::ORDER_ALREADY_CANCELLED]))) {
    $redis->set($keyOrderCodeConfirmation, 1, $customIfood['expire_order_code_confirmation']);
    $result['success'] = true;
    Util::sendJson($result);
}

if ($useWebhook || $responseCode != RequestConstants::HTTP_NOT_FOUND) {
    if ($errorCode != DeliveryConfirmationError::MAX_ATTEMPTS_ON_CODE_CONFIRMATION) {
        $redis->sAdd($keyOrderCodeInvalid, $code);
        $redis->expire($keyOrderCodeInvalid, $customIfood['expire_order_code_invalid'], 'NX');
    }
    $result['content'] = $response?->content ?? [];
    Util::sendJson($result);
}

if (!dispatchOrder($orderId, $oauth->accessToken, $useWebhook)) {
    $result['content'] = ['message' => 'Error dispatch order'];
    Util::sendJson($result);
}

// Necessário para que o dispatch do pedido no iFood aconteça antes da segunda requisição
// O cenário onde o dispatch é necessário ao confirmar o código trata-se de uma exceção
sleep(1);

$response = requestConfirmDelivery($oauth, $orderId, $code, $useWebhook);
$responseCode = $response?->http_code;

if ($responseCode == RequestConstants::HTTP_OK) {
    $redis->set($keyOrderCodeConfirmation, 1, $customIfood['expire_order_code_confirmation']);
    $result['success'] = true;
    Util::sendJson($result);
}

$result['content'] = $response?->content ?? [];
Util::sendJson($result);
