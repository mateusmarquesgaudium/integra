<?php
/**
 * Realiza a verificação de tempo em tempo para saber quais empresas estão com a integração ativa com o sistema
 */
require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/ifood/Service.php';
require_once __DIR__ . '/../../../../src/Database.php';
require_once __DIR__ . '/../../../../src/ifood/IfoodServiceV2.php';

use src\ifood\Enums\OauthMode;
use src\ifood\Enums\RedisSchema;

// Conexão com o banco de dados Redis
$redis = connectionRedis($custom['redis']['hostname'], $custom['redis']['port']);

function status($redis, $customIfood, $redisMerchants) {
    $expireStatus = 3 * 60 * 60; // 3 Horas (em segundos)

    $useWebhook = !empty($redis->get(RedisSchema::KEY_ENABLE_WEBHOOK));
    $oauthMode = !$useWebhook ? OauthMode::POLLING : OauthMode::WEBHOOK;

    // Gera o token ou pega algum que está em cache no Redis
    $token = createToken($oauthMode);
    if ( empty($token) ) {
        return [
            'success' => false,
            'message' => 'Não foi possível criar um token de acesso.'
        ];
    }

    // Inicia o processo de busca das empresas ativas para manipular os pedidos
    $currentPage = 1;
    $limitPage = 100;
    $merchants = [];
    while ( $currentPage !== false ) {
        $code = $codeError = $messageError = null;
        $response = callUrl($customIfood['uri'] . '/merchant/v1.0/merchants?page=' . $currentPage . '&size=' . $limitPage, $code, $codeError, $messageError, 5000, [
            'header' => ['Authorization: Bearer ' . $token],
            'log' => true
        ]);
        if ($code != 204 && $code != 202 && $code != 200) {
            $merchants = [];
            break;
        }
        if ( !is_array($response) ) {
            $currentPage = false;
            break;
        }
        $merchants = array_merge($merchants, $response);
        if ( count($response) < $limitPage ) {
            $currentPage = false;
            break;
        }
        $currentPage++;
    }
    if ( empty($merchants) || !is_array($merchants) ) {
        return [
            'success' => false,
            'message' => 'Erro ao buscar os merchants.'
        ];
    }

    // Percorre as empresas encontradas e salva no banco de dados na chave de ativos
    $merchantsIds = array_column($merchants, 'id');
    $merchantsNames = array_column($merchants, 'name');
    $merchants = null;
    if (!empty($merchantsIds) && !empty($merchantsNames)) {
        $merchants = array_combine($merchantsIds, $merchantsNames);
    }

    if (!empty($merchants)) {
        $webhookMerchants = [];

        $keys = $redis->keys($redisMerchants . '*');
        $values = $redis->mget($keys);
        $keys = str_replace($redisMerchants, '', $keys);

        // Merchants armazenados no redis
        if (!empty($keys)) {
            foreach (array_combine($keys, $values) as $merchantId => $data) {
                $data = json_decode($data, true);
                if ($data === false) {
                    continue;
                }

                $status = $data['status'] ?? 'A';
                $name = $merchants[$merchantId] ?? $data['name'] ?? '';

                if (array_key_exists($merchantId, $merchants)) {
                    unset($merchants[$merchantId]);
                    if ($status == 'D' || $data['name'] != $name) {
                        $merchantData = [
                            'status' => 'A',
                            'name' => $name
                        ];

                        $redis->del($redisMerchants . $merchantId);
                        $redis->set($redisMerchants . $merchantId, json_encode($merchantData));
                        $status = 'A';
                    }
                } elseif ($status == 'A') {
                    $data['status'] = 'D';

                    $redis->set($redisMerchants . $merchantId, json_encode($data));
                    $redis->expire($redisMerchants . $merchantId, $expireStatus);
                    $status = 'D';
                }

                $webhookMerchants[] = [
                    'uuid' => $merchantId,
                    'name' => $name,
                    'status' => $status,
                ];
            }
        }

        // Percorre os merchants que sobraram na lista do iFood
        if (!empty($merchants)) {
            foreach ($merchants as $merchantId => $name) {
                $merchantData = [
                    'status' => 'A',
                    'name' => $name
                ];

                $redis->set($redisMerchants . $merchantId, json_encode($merchantData));

                $webhookMerchants[] = [
                    'uuid' => $merchantId,
                    'name' => $name,
                    'status' => 'A',
                ];
            }
        }

        // Notifica o webhook o status das empresas
        if ( !empty($webhookMerchants) ) {
            $options = [
                'metodo' => 'POST',
                'header' => [
                    'Content-Type: application/json'
                ],
                'dados' => ['empresas' => $webhookMerchants],
                'postfield_opt' => 'json_encode',
                'followLocation' => true,
                'log' => true
            ];
            $response = callUrl2($customIfood['taximachine_ifood_empresa_status'], null, $options);
            if ( isset($response['status']) && !$response['status'] || !isset($response['status']) ) {
                return [
                    'success' => false
                ];
            }
        }
    }

    return [
        'success' => true
    ];
}

$otherExecution = !isset($otherExecution) ? false : $otherExecution;
// Monitor
if (!$otherExecution) {
    checkMonitor($redisMonitorMerchants);
    $redis->incr($redisMonitorMerchants);
}

$result = status($redis, $customIfood, $redisMerchants);
if (!$otherExecution) {
    $redis->decr($redisMonitorMerchants);
    sendJsonStructure($result);
}