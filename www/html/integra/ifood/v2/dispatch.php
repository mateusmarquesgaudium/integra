<?php
/**
 * Realiza o despacho dos pedidos informados que estão em cache.
 */
require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/ifood/Service.php';
require_once __DIR__ . '/../../../../src/Database.php';
require_once __DIR__ . '/../../../../src/ifood/IfoodServiceV2.php';

use src\ifood\Enums\OauthMode;
use src\ifood\Enums\RedisSchema;

// Conexão com o banco de dados Redis
$redis = connectionRedis($custom['redis']['hostname'], $custom['redis']['port']);

// Verifica no monitor
checkMonitor($redisMonitorDispatch);
$redis->incr($redisMonitorDispatch);

// Gera o token ou pega algum que está em cache no Redis
$token = createToken(OauthMode::POLLING);
if ( empty($token) ) {
    $redis->decr($redisMonitorDispatch);
    sendJsonStructure([
        'success' => false,
        'message' => 'Não foi possível criar um token de acesso.'
    ]);
}

// Lista de pedidos para marcar como despachado no ifood
$orders = $redis->sMembers($redisDispatch);
if ( !empty($orders) ) {
    $redis->del($redisDispatch);

    foreach ($orders as $orderId) {
        if (!dispatchOrder($orderId, $token, false)) {
            $redis->sAdd($redisDispatch, $orderId);
        }
    }
}

$redis->decr($redisMonitorDispatch);
sendJsonStructure([
    'success' => true,
    'response' => [
        'total' => sizeof($orders)
    ]
]);
