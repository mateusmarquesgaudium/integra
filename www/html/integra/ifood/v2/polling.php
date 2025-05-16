<?php
/**
 * Realiza o polling diretamento no iFood para buscar novos eventos em pedidos para armazenar no banco de dados Redis.
 * Todos os eventos são agrupados pelo ID do pedido e possuem uma ordenação pela data da criação do evento (createdAt)
 */
require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/ifood/Service.php';
require_once __DIR__ . '/../../../../src/Database.php';
require_once __DIR__ . '/../../../../src/ifood/IfoodServiceV2.php';

use src\geral\RedisService;
use src\ifood\Enums\OauthMode;
use src\ifood\Enums\RedisSchema;

// Seta as variáveis necessárias para o funcionamento
$expireOrder = 60 * 30; // 30 Minutos (em segundos)
$expireOrderDetails = 3 * 60 * 60; // 3 Horas (em segundos)

// Conexão com o banco de dados Redis
$redis = new RedisService;
$redis->connectionRedis($custom['redis']['hostname'], $custom['redis']['port']);

// Verifica se polling está desativado
$pollingDisable = !empty($redis->get(RedisSchema::KEY_DISABLE_POLLING));
if ($pollingDisable) {
    exit;
}

// Código para verificar questões do webhook
$useWebhook = !empty($redis->get(RedisSchema::KEY_ENABLE_WEBHOOK));
if ($useWebhook) {
    // Chave para armazenar a última execução
    $lastRunKey = 'it:if2:mt:webhook';

    // Obter a última execução salva no Redis
    $lastRun = $redis->get($lastRunKey);

    // Verificar se a última execução foi há menos de 30 minutos
    $currentTime = time();
    if ($lastRun && ($currentTime - $lastRun) < 1800) { // 1800 segundos = 30 minutos
        // Sair do script se não tiver passado 30 minutos desde a última execução
        exit;
    }

    $redis->set($lastRunKey, $currentTime);
}

// Verifica no monitor
checkMonitor($redisMonitorEvents);
$redis->incr($redisMonitorEvents);

$oauthMode = !$useWebhook ? OauthMode::POLLING : OauthMode::WEBHOOK;
$ifoodModule = $useWebhook ? 'logistics' : 'order';

// Gera o token ou pega algum que está em cache no Redis
$token = createToken($oauthMode);
if ( empty($token) ) {
    $redis->decr($redisMonitorEvents);
    sendJsonStructure([
        'success' => false,
        'message' => 'Não foi possível criar um token de acesso.'
    ]);
}

// Separa os merchants em grupos de 100 para enviar no polling
$listMerchants = null;
foreach ($redis->keys($redisMerchants . '*') as $keyOrder) {
    $data = $redis->get($keyOrder);
    $data = json_decode($data, true);
    if ($data !== false && ($data['status'] == 'A' || $data == 'A')) {
        setListMerchants($listMerchants, str_replace($redisMerchants, '', $keyOrder));
    }
}

if ( !empty($listMerchants) ) {
    if (!$useWebhook) {
        $endpointPolling = '/order/v1.0/events:polling';
        $endpointAcknowledgment = '/order/v1.0/events/acknowledgment';
    } else {
        $endpointPolling = '/events/v1.0/events:polling?excludeHeartbeat=true';
        $endpointAcknowledgment = '/events/v1.0/events/acknowledgment';
    }

    foreach ( $listMerchants as $merchants ) {
        // Inicia o processo de polling no iFood buscando todos os eventos que precisam ser processados
        $code = $codeError = $messageError = NULL;
        $pollingResponse = callUrl($customIfood['uri'] . $endpointPolling, $code, $codeError, $messageError, 15000, [
            'header' => ['Authorization: Bearer ' . $token, 'x-polling-merchants:' . implode(',', $merchants)],
            'log' => true
        ]);
        if ( !verifyErrorPolling($code) ) {
            $redis->decr($redisMonitorEvents);
            sendJsonStructure([
                'success' => false,
                'message' => 'Erro na API do iFood.'
            ]);
        }
    
        if ( isset($pollingResponse['message']) ) {
            $redis->decr($redisMonitorEvents);
            sendJsonStructure([
                'success' => false,
                'message' => $pollingResponse['message']
            ]);
        }
    
        $listAcknowledgment = null;
    
        // Ajusta todos os eventos do ifood para separar por pedido, tendo a lista de eventos do pedido ordenado pela data/hora do ocorrido (ordenação mais a frente)
        $pollings = [];
        if ( is_array($pollingResponse) ) {
            foreach ( $pollingResponse as $order ) {
                unset($order['metadata']);
                if ( !isset($pollings[$order['orderId']]) ) {
                    $pollings[$order['orderId']] = [];
                }
                $pollings[$order['orderId']][] = $order;
                setListAcknowledgment($listAcknowledgment, ['id' => $order['id']]);
            }
        }
    
        // Percorre todos os eventos e armazena no redis para obter os dados do pedido
        foreach ( $pollings as $orderId => $orders ) {
            // Busca os eventos do pedido já cadastrados e logo em seguida já ordena pela data da criação
            while ( $redisOrder = $redis->rPop($redisEvents . $orderId) ) {
                $orders[] = json_decode($redisOrder, true);
            }
            usort($orders, 'sortOrders');
            $orders = array_map('unserialize', array_unique(array_map('serialize', $orders)));
    
            $orderConfirmed = false;
            // Armazena os eventos do pedido
            foreach ( $orders as $order ) {
                $redis->rPush($redisEvents . $orderId, json_encode($order));
    
                $orderConfirmed =  $orderConfirmed|| $order['fullCode'] == 'CONFIRMED';
            }
            $redis->expire($redisEvents . $orderId, $expireOrder);
    
            // Se possuir a confirmaçao do evento obtém os dados do pedido para ser salvo.
            if ( $orderConfirmed && !$redis->get($redisOrderDetails . $orderId) ) {
                $orderDetails = callUrl2($customIfood['uri'] . '/' . $ifoodModule . '/v1.0/orders/' . $orderId, 8000, [
                    'header' => ['Authorization: Bearer ' . $token],
                    'log' => true
                ]);
                if ( !empty($orderDetails) ) {
                    // Seta o endereço de entrega quando o pedido é um teste
                    if (isset($orderDetails['isTest']) && $orderDetails['isTest'] && isset($orderDetails['delivery']['deliveryAddress'])) {
                        $orderDetails['delivery']['deliveryAddress'] = [
                            'streetName' => 'Ramal Bujari',
                            'streetNumber' => '100',
                            'formattedAddress' => 'Ramal Bujari, 100',
                            'neighborhood' => 'Bujari',
                            'complement' => 'Complemento TESTE',
                            'postalCode' => '69926000',
                            'city' => 'Bujari',
                            'state' => 'AC',
                            'country' => 'BR',
                            'reference' => 'Referência TESTE',
                            'coordinates' => [
                                'latitude' => -9.822159000,
                                'longitude' => -67.948475000,
                            ],
                        ];
                    }
                    $redis->set($redisOrderDetails . $orderId, json_encode($orderDetails));
                    $redis->expire($redisOrderDetails . $orderId, $expireOrderDetails);
                }
            }
        }
    
        // Retorna para o iFood quais eventos dos pedidos que precisam ser limpo do polling
        if ( !empty($listAcknowledgment) ) {
            $optionsAcknowledgment = [
                'metodo' => 'POST',
                'header' => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                ],
                'postfield_opt' => 'json_encode',
                'followLocation' => true,
                'log' => true
            ];
            foreach ( $listAcknowledgment as $acknowledgments ) {
                $optionsAcknowledgment['dados'] = $acknowledgments;
                callUrl2($customIfood['uri'] . $endpointAcknowledgment, 8000, $optionsAcknowledgment);
            }
        }
    }

    $redis->decr($redisMonitorEvents);
    sendJsonStructure([
        'success' => true
    ]);
}

$redis->decr($redisMonitorEvents);
sendJsonStructure([
    'success' => false
]);