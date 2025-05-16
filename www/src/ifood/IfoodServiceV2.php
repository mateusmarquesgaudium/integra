<?php
/**
 * Arquivo responsável por armazenar todos as funções utilizadas no microserviço do iFood
 */

 /**
  * Variáveis que são utilizadas no serviço do iFood
  */
$customIfood = isset($custom['params']['ifood']) ? $custom['params']['ifood'] : [];
$redisTokenOauth = 'it:ifood:oauth:token';
$redisEvents = 'it:if2:evt:';
$redisOrderDetails = 'it:if2:dados:';
$redisMerchants = 'it:if2:merchants:';
$redisDispatch = 'it:if2:dispatch';
$redisMaxPollingTry = 'it:if2:polling:erros:count';
$redisErrorsCognizant = 'it:if2:polling:erros:cognizant';
$redisErrorsNotification = 'it:if2:polling:erros:notification';
$redisOrderCodeConfirmation = 'it:if2:code:{orderId}';

$redisMonitorEvents = 'it:if2:mt:evt';
$redisMonitorDispatch = 'it:if2:mt:dispatch';
$redisMonitorMerchants = 'it:if2:mt:merchants';

$codeOrderNotFoundDispatch = 'OrderNotFound';

require_once __DIR__ . '/../autoload.php';

use src\geral\{
    RedisService,
    Request
};
use src\ifood\Http\Oauth;
use src\geral\Enums\RequestConstants;
use src\ifood\Entities\ArrivedAtDestination;
use src\ifood\Enums\DeliveryStatus;
use src\ifood\Enums\RedisSchema;

/**
 * Função responsável por criar o token de acesso ou retornar o token que está em cache
 * 
 * @return STRING|NULL = Retorna o token ou null em casos de erros.
 */
function createToken(string $oauthMode): ?string {
    global $redis;

    try {
        $oauth = new Oauth($redis, $oauthMode);
        $token = $oauth->accessToken;
        return $token;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Função responsável por o Oauth e retornar
 *
 * @param RedisService $redisService instância do Redis
 * @param string $oauthMode modo de autenticação
 * @return Oauth|null retorna o Oauth ou null em caso de erro
 */
function createOauth(RedisService $redisService, string $oauthMode): ?Oauth {
    try {
        $oauth = new Oauth($redisService, $oauthMode);
        if (empty($oauth->accessToken)) {
            return null;
        }
        return $oauth;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Ordena uma lista de eventos pela chave createdAt.
 * 
 * @param ARRAY $a  = Recebe o primeiro a ser verificado.
 * @param ARRAY $b  = Recebe o segundo a ser verificado.
 * @return ARRAY    = Retorna o array ordenado.
 */
function sortOrders($a, $b) {
    return strcasecmp($a['createdAt'], $b['createdAt']);
}

/**
 * Seta na lista de eventos que precisam ser excluídos colocando no maxímo 1995 pedidos por index.
 * 
 * @param ARRAY $acknowledgments    = Lista de eventos que serão deletados do polling
 * @param ARRAY $order              = Pedido retornado no polling
 */
function setListAcknowledgment(&$acknowledgments, $order) {
    if ( is_null($acknowledgments) ) {
        $acknowledgments[] = [];
    }
    $key = key(array_slice($acknowledgments, -1, 1, true));
    $maxAcknowledgmentPerRequest = 1995;
    if ( is_array($acknowledgments[$key]) && count($acknowledgments[$key]) < $maxAcknowledgmentPerRequest ) {
        $acknowledgments[$key][] = $order;
    } else {
        $acknowledgments[][] = $order;
    }
}

/**
 * Seta na lista dos merchants para realizar o polling em grupos 100 merchants por vez
 * 
 * @param ARRAY $merchants      = Lista de merchants agrupados
 * @param ARRAY $merchant      = ID do merchants
 */
function setListMerchants(&$merchants, $merchant) {
    if ( is_null($merchants) ) {
        $merchants[] = [];
    }
    $key = key(array_slice($merchants, -1, 1, true));
    $maxMerchantPerRequest = 100;
    if ( is_array($merchants[$key]) && count($merchants[$key]) < $maxMerchantPerRequest ) {
        $merchants[$key][] = $merchant;
    } else {
        $merchants[][] = $merchant;
    }
}

/**
 * Deleta o token que está em cache.
 * 
 * @param BOOL $force   = True para forçar apagar o token e False para seguir o fluxo normal.
 * @return BOOL         = Retorna se o token realmente foi deletado.
 */
function clearToken($force): bool {
    global $redis;
    global $redisTokenOauth;

    // O valor 21300 é calculado subtraindo 300s (5 min) de margem de segurança do tempo total de vida do token (21600s ou 6h).
    $timeDifference = 21300 - $redis->ttl($redisTokenOauth);
    // Deleta o token em cache somente se já faz mais de 30 minutos que o mesmo foi gerado ou se foi informado que é necessário forçar a exclusão.
    if ( $timeDifference > 1800 || $force ) {
        return $redis->del($redisTokenOauth);
    }
    return false;
}

/**
 * Verificar se o polling deu erro com base no código do header
 * 
 * @param INTEGER $code = Código do header retornado na API do iFood
 * @return BOOL         = Retorna se é necessário parar a execução do código ou não
 */
function verifyErrorPolling($code) {
    global $redis;
    global $customIfood;
    global $redisMaxPollingTry;
    global $redisErrorsCognizant;
    global $redisErrorsNotification;

    $newStatus = null;
    if ( $code != 204 && $code != 202 && $code != 200 ) {
        $redis->incr($redisMaxPollingTry, 1);
        $newStatus = 'I';
    }
    
    $currentTry = $redis->get($redisMaxPollingTry);
    if ( !$newStatus && $currentTry > 0 ) {
        $redis->set($redisMaxPollingTry, 0);
        $redis->del($redisErrorsCognizant, $redisErrorsNotification);
    }

    // Verifica se é necessário enviar a notificação ao webhook
    if ( $currentTry && $currentTry >= $customIfood['max_polling_try'] ) {
        $notification = 0;
        $errorsCognizant = $redis->get($redisErrorsCognizant);
        $errorsNotification = $redis->get($redisErrorsNotification);
        if ( !$errorsCognizant && !$errorsNotification ) {
            $notification = 1;
            $redis->set($redisErrorsNotification, 1);
            $redis->expire($redisErrorsNotification, $customIfood['time_minutes_error_notification'] * 60);
        }

        $newStatus = !$newStatus ? 'D' : $newStatus;
        $requestOptions = [
            'metodo' => 'POST',
            'log' => true,
            'dados' => [
                'status' => $newStatus,
                'notification' => $notification
            ]
        ];
        callUrl2($customIfood['taximachine_ifood_disponivel'], 5000, $requestOptions);
    }

    if ( $newStatus == 'I' ) {
        // Limpa o token e força a atualização do status das empresa
        if ( $currentTry == 1 && ($code == 400 || $code == 403) ) {
            clearToken(true);
            executeStatus();
        }
        return false;
    }
    return true;
}

/**
 * Verifica se a rotina pode gerar através do monitor, se não puder retorna false no header
 * 
 * @param STRING $monitor = Chave do monitor que deseja ser verificado
 */
function checkMonitor($monitor) {
    global $redis;

    $monitor = $redis->get($monitor);
    if ( $monitor > 0 ) {
        sendJsonStructure([
            'success' => false,
            'message' => 'Já em execução.'
        ]);
    }
}

/**
 * Chama o arquivo de status para ser executado e atualizado o status dos merchants
 */
function executeStatus() {
    global $custom, $customIfood, $redisMonitorMerchants, $redisMerchants;
    $otherExecution = true;
    require_once __DIR__ . '/../../html/integra/ifood/v2/status.php';
}

/**
 * Função que faz uma tentativa de despacho de uma solicitação
 *
 * @param int $orderId Id da solicitação
 * @param string $token Token de acesso
 * @param bool $useWebhook Se é para usar o webhook ou não
 * @return bool Retorna se foi possível fazer o despacho
 */
function dispatchOrder($orderId, $token, bool $useWebhook) {
    global $customIfood, $codeOrderNotFoundDispatch;

    $ifoodModule = $useWebhook ? 'logistics' : 'order';

    $code = $codeError = $messageError = NULL;
    $dispatchResponse = callUrl($customIfood['uri'] . '/' . $ifoodModule . '/v1.0/orders/' . $orderId . '/dispatch', $code, $codeError, $messageError, 5000, [
        'metodo' => 'POST',
        'header' => [
            'Authorization: Bearer ' . $token,
            'Content-Length: 0'
        ],
        'log' => true
    ]);

    $responseCodeError = $dispatchResponse['error'] ?? [];
    $responseCodeError = $responseCodeError['code'] ?? null;

    if ($code != 204 && $code != 202 && !($code == 404 && $responseCodeError == $codeOrderNotFoundDispatch)) {
        return false;
    }

    return true;
}

/**
 * Realiza a requisição de confirmação do pedido do ifood
 *
 * @param Oauth $oauth Objeto de autenticação
 * @param int $orderId Id da solicitação
 * @param int $code Código de confirmação do pedido
 * @param bool $useWebhook Se é para usar o webhook ou não
 * @return stdClass Dados do request de confirmação do delivery
 */
function requestConfirmDelivery(Oauth $oauth, $orderId, $code, $useWebhook) {
    global $customIfood;
    global $redis;

    // Envia o ARRIVED_AT_DESTINATION para o iFood
    if ($useWebhook) {
        /**
         * Possíveis problemas para o futuro:
         * - Ratelimit
         * - Processo em paralelo não rodou o envio do envento anterior
         */
        $arrivedAtDestination = new ArrivedAtDestination($oauth, $redis);
        $resultado = $arrivedAtDestination->send([
            'orderId' => $orderId,
            'webhookType' => DeliveryStatus::ARRIVED_AT_DESTINATION,
        ]);

        if (!$resultado) {
            $keyEventRetry = str_replace(['{order_id}', '{event_type}'], [$orderId, DeliveryStatus::ARRIVED_AT_DESTINATION], RedisSchema::KEY_RETRY_ORDER_EVENTS);
            $redis->del($keyEventRetry);
        }
    }

    $urlIfood = $customIfood['uri'];
    $urlIfood .= $useWebhook ? "/logistics/v1.0/orders/{$orderId}/verifyDeliveryCode" : "/orders/v1.0/order/{$orderId}/confirmDelivery";

    $request = new Request($urlIfood);
    $request
        ->setHeaders([
            'Content-Type: application/json',
            'Authorization: Bearer ' . $oauth->accessToken,
        ])
        ->setRequestMethod('POST')
        ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
        ->setPostFields([
            'code' => $code,
        ])
        ->setSaveLogs(true);

    return $request->execute();
}