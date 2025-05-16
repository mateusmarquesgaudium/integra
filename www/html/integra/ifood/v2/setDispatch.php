<?php
/**
 * Recebe o ID do pedido que deve ser despachado no iFood e armazena no redis para posteriormente ser efetivado (dispatch.php)
 */
require_once __DIR__ . '/../../../../src/ifood/Service.php';
require_once __DIR__ . '/../../../../src/Database.php';
require_once __DIR__ . '/../../../../src/ifood/IfoodServiceV2.php';

use src\ifood\Enums\RedisSchema;

// Conexão com o banco de dados Redis
$redis = connectionRedis($custom['redis']['hostname'], $custom['redis']['port']);

$postForm = filter_input_array(INPUT_GET, FILTER_DEFAULT);
if ( empty($postForm['pedido_id']) ) {
    sendJsonStructure([
        'success' => false,
        'message' => 'Por favor informe o ID do pedido corretamente.'
    ]);
}

$useWebhook = !empty($redis->get(RedisSchema::KEY_ENABLE_WEBHOOK));
if (!$useWebhook) {
    $redis->sAdd($redisDispatch, $postForm['pedido_id']);
}
sendJsonStructure([
    'success' => true,
]);
