<?php
/**
 * Apaga o token do ifood para obter polling com os novos restaurantes
 */
require_once __DIR__ . '/../../../../src/ifood/Service.php';
require_once __DIR__ . '/../../../../src/Database.php';
require_once __DIR__ . '/../../../../src/ifood/IfoodServiceV2.php';

// Conexão com o banco de dados Redis
$redis = connectionRedis($custom['redis']['hostname'], $custom['redis']['port']);

// Apaga o token de acesso que está em cache
$force = (isset($_GET['forcar']) && ($_GET['forcar'] == '1' || $_GET['forcar'] == 'true') ) ? true : false;
if ( !clearToken($force) ) {
    sendJsonStructure([
        'success' => false,
    ]);
}

sendJsonStructure([
    'success' => true,
]);