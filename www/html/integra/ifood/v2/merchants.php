<?php
/**
 * Endpoint que disponibiliza os merchants que estão aparecendo na integração
 */
require_once __DIR__ . '/../../../../src/ifood/Service.php';
require_once __DIR__ . '/../../../../src/Database.php';
require_once __DIR__ . '/../../../../src/ifood/IfoodServiceV2.php';

// Conexão com o banco de dados Redis
$redis = connectionRedis($custom['redis']['hostname'], $custom['redis']['port']);

// Busca os merchants e retorna
$merchants = [];
foreach ( $redis->keys($redisMerchants . '*') as $keyOrder ) {
    $data = $redis->get($keyOrder);
    $data = json_decode($data, true);

    $merchants[] = [
        'uuid' => str_replace($redisMerchants, '', $keyOrder),
        'name' => $data['name'] ?? null,
        'status' => ($data['status'] ?? 'A') == 'A' ? true : false
    ];
}

sendJsonStructure([
    'success' => true,
    'resultado' => $merchants
]);