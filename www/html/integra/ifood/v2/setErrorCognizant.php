<?php
/**
 * Informa que a Machine está ciente dos erros que estão dando
 */
require_once __DIR__ . '/../../../../src/ifood/Service.php';
require_once __DIR__ . '/../../../../src/Database.php';
require_once __DIR__ . '/../../../../src/ifood/IfoodServiceV2.php';

// Conexão com o banco de dados Redis
$redis = connectionRedis($custom['redis']['hostname'], $custom['redis']['port']);

$currentTry = $redis->get($redisMaxPollingTry);
if ( !$currentTry || $currentTry < $customIfood['max_polling_try'] ) {
    sendJsonStructure([
        'success' => false,
        'message' => 'O microserviço não está indisponível para marcar como ciente.'
    ]);
}

$redis->set($redisErrorsCognizant, 1);
sendJsonStructure([
    'success' => true,
]);
