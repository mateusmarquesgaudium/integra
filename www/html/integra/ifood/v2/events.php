<?php
/**
 * Serviço que disponibiliza os eventos de todos os pedidos do iFood que estão em cache.
 */
require_once __DIR__ . '/../../../../src/ifood/Service.php';
require_once __DIR__ . '/../../../../src/Database.php';
require_once __DIR__ . '/../../../../src/ifood/IfoodServiceV2.php';

// Conexão com o banco de dados Redis
$redis = connectionRedis($custom['redis']['hostname'], $custom['redis']['port']);

// Verifica se foi informado a data e hora que serve para limitar os pedidos cujo o último foi posterior a data e hora informada.
$getForm = filter_input_array(INPUT_GET, FILTER_DEFAULT);
$limitCreatedAt = NULL;
if ( !empty($getForm['data']) && !empty($getForm['hora']) ) {
    if ( !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $getForm['data']) || !checkdate(substr($getForm['data'], 5, 2), substr($getForm['data'], 8, 2), substr($getForm['data'], 0, 4)) || strtotime($getForm['hora']) === false ) {
        sendJsonStructure([
            'success' => false,
            'message' => 'Data e/ou hora inválidos.'
        ]);
    }
    $limitCreatedAt = $getForm['data'] . 'T' . $getForm['hora'] . '.000Z';
}

// Busca todos os eventos de cada pedido e retorna
$events = [];
foreach ( $redis->keys($redisEvents . '*') as $keyOrder ) {
    $orderEvents = $redis->lRange($keyOrder, 0, -1);
    // Verifica se a data o último evento já está antes da data limite
    if ( $limitCreatedAt && json_decode($orderEvents[sizeof($orderEvents) - 1], true)['createdAt'] >= $limitCreatedAt || is_null($limitCreatedAt) ) {
        foreach ( $orderEvents as $order ) {
            $order = json_decode($order, true);
            $orderId = $order['orderId'];

            if ( !isset($events[$orderId]) ) {
                $orderDetails = $redis->get($redisOrderDetails . $orderId);
                $events[$orderId] = [
                    'id'        => $orderId,
                    'eventos'   => [],
                    'dados'     => json_decode($orderDetails, true)
                ];
            }
            $events[$orderId]['eventos'][] = $order;
        }
    } else {
        break;
    }
}

sendJsonStructure([
    'success' => true,
    'resultado' => $events
]);