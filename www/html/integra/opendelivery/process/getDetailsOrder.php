<!--
    Este processo tem como responsabilidade obter os detalhes dos pedidos nos provedores
    e enviar para a fila de eventos, sendo assim ele é executado a cada tempo e verifica
    se há pedidos na fila 'it:opendel:ord:gd' e passa por um processo para obter os detalhes
    dos pedidos.

    Os pedidos utilizam o campo orderDetailsURL para obter os detalhes do pedido, após obter
    os detalhes do pedido, é verificado se o status do pedido foi bem sucedido, caso não seja,
    o pedido é reenviado para a fila até que o número máximo de tentativas seja atingido.
-->
<?php

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use src\geral\RequestMulti;
use src\geral\Util;
use src\Opendelivery\Enums\RedisSchema;
use src\Opendelivery\Handlers\GetAccessTokenHandler;
use src\Opendelivery\Service\Order;
use src\Opendelivery\Service\ProviderCredentials;

$custom = new Custom;
$redisService = new RedisService;
$redisService->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxInstances = 1;
$redisService->checkMonitor(RedisSchema::KEY_GET_DETAILS_ORDER_MONITOR, $maxInstances);
$redisService->incrMonitor(RedisSchema::KEY_GET_DETAILS_ORDER_MONITOR);

try {
    $maxRequestsForTime = $custom->getOpenDelivery()['maxEvents'];
    $orders = $redisService->lRange(RedisSchema::KEY_EVENTS_GET_DETAILS_ORDER, 0, $maxRequestsForTime);
    if (empty($orders)) {
        throw new UnderflowException('No orders to process');
    }

    $redisService->lTrim(RedisSchema::KEY_EVENTS_GET_DETAILS_ORDER, count($orders), -1);

    $providersAccessTokens = [];
    foreach ($orders as $order)
    {
        $order = json_decode($order, true);
        $provider = $order['provider'];
        $merchantId = $order['merchantId'] ?? '';

        $providerCredentials = new ProviderCredentials($redisService, $custom, $provider);
        $getAccessTokenHandler = new GetAccessTokenHandler(
            new Request($custom->getOpendelivery()['provider'][$provider]['url'] . '/oauth/token'),
            $redisService,
            $provider,
            $providerCredentials->getClientId($merchantId),
            $providerCredentials->getClientSecret($merchantId),
            $merchantId
        );

        if (!isset($providersAccessTokens[$provider]) && empty($merchantId)) {
            $providersAccessTokens[$provider] = $getAccessTokenHandler->execute();
        }

        if (!isset($providersAccessTokens[$provider]) && !empty($merchantId)) {
            $providersAccessTokens[$provider][$merchantId] = $getAccessTokenHandler->execute();
        }
    }

    $requests = [];
    $pipeline = [];
    foreach ($orders as $order) {
        $order = json_decode($order, true);
        $request = new Request($order['orderURL']);

        $merchantId = $order['merchantId'] ?? '';
        $accessToken = '';
        if (!empty($merchantId)) {
            $accessToken = $providersAccessTokens[$order['provider']][$merchantId];
        } else {
            $accessToken = $providersAccessTokens[$order['provider']];
        }

        if (empty($accessToken)) {
            continue;
        }

        $request
            ->setRequestMethod('GET')
            ->setHeaders([
                "Authorization: Bearer {$accessToken}",
            ])
            ->setSaveLogs(true);

        $requests[$order['orderId']] = $request;
    }

    $requestMulti = new RequestMulti($requests);
    $responses = $requestMulti->execute();

    foreach ($orders as $order) {
        $order = json_decode($order, true);
        $orderDetail = $responses[$order['orderId']] ?? null;
        if (empty($orderDetail)) {
            continue;
        }

        if ($orderDetail->http_code === RequestConstants::HTTP_UNAUTHORIZED) {
            $key = str_replace('{provider}', $provider, RedisSchema::KEY_ACCESS_TOKEN_PROVIDER);
            $pipeline[] = ['del', $key];
            $pipeline[] = ['lPush', $keyOrderDetails, json_encode($order)];
            continue;
        }

        if ($orderDetail->http_code === RequestConstants::HTTP_NOT_FOUND) {
            continue;
        }

        if ($orderDetail->http_code !== RequestConstants::HTTP_OK) {
            $order['nextMultiplierToRetry'] = isset($order['nextMultiplierToRetry']) ? $order['nextMultiplierToRetry'] * 2 : 1;
            $order['nextTimeToRetry'] = Util::getNextTimeToRetry($order['nextMultiplierToRetry'], $customAiqfome['timeToRetry']);
            $order['attempts'] = ($order['attempts'] ?? 0) + 1;
            $order['errors'][] = [
                'http_code' => $orderDetail->http_code,
                'content' => $orderDetail->content,
            ];

            $pipeline[] = ['lPush', $keyOrderDetails, json_encode($order)];
            continue;
        }

        $orderDetailContent = json_decode($orderDetail->content, true);
        $orderService = new Order($redisService, $provider);
        $orderService->setOrderToSendWebhook($orderDetailContent);
    }

    $redisService->pipelineCommands($pipeline);
} catch (\UnderflowException $th) {
    echo $th->getMessage() . PHP_EOL;
} catch (\Throwable $th) {
    MchLog::logAndInfo('log_opendelivery', MchLogLevel::ERROR, [
        'trace' => $th->getTraceAsString(),
        'message' => $th->getMessage(),
    ]);
}

$redisService->decr(RedisSchema::KEY_GET_DETAILS_ORDER_MONITOR);
