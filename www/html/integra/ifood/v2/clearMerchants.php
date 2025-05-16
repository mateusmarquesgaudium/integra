<?php
/**
 * Endpoint para deletar os merchants que estão em cache
 */
require_once __DIR__ . '/../../../../src/ifood/Service.php';
require_once __DIR__ . '/../../../../src/Database.php';
require_once __DIR__ . '/../../../../src/ifood/IfoodServiceV2.php';

// Conexão com o banco de dados Redis
$redis = connectionRedis($custom['redis']['hostname'], $custom['redis']['port']);

// Deleta os merchants
if ( !empty($redisMerchants) ) {
    $keysDeleted = $redis->del($redis->keys($redisMerchants . '*'));

    sendJsonStructure([
        'success' => $keysDeleted !== false,
        'keys_deleted' => (int) $keysDeleted
    ]);
}

sendJsonStructure([
    'success' => false,
    'message' => 'Erro grave ao tentar encontrar a variável que possuí o diretório em cache dos merchants.'
]);