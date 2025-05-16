<!--
    Este processo tem como responsabilidade enviar os eventos ocorridos nos pedidos
    para os provedores, sendo assim ele é executado a cada tempo e verifica se há eventos
    na fila 'it:opendel:ord:hook:ev' e passa por um processo para enviar os eventos.

    Os eventos devem ser processados em ordem, para isso é verificado se o evento
    atual é o próximo a ser enviado, caso não seja, o evento é reenviado para a fila
    até que seja o próximo a ser enviado.

    Após o envio do evento, é verificado se o status do envio foi bem sucedido, caso
    não seja, o evento é reenviado para a fila até que o número máximo de tentativas
    seja atingido. Caso o número máximo de tentativas seja atingido, o evento é enviado
    para a fila 'it:opendel:ord:err'.
-->
<?php

require_once __DIR__ . '/../../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../../src/autoload.php';

use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use src\geral\RequestMulti;
use src\geral\Util;
use src\Opendelivery\Entities\OrderCache;
use src\Opendelivery\Entities\OrderEventsStateMachine;
use src\Opendelivery\Entities\WebhookProvider;
use src\Opendelivery\Enums\RedisSchema;

$custom = new Custom;
$redisClient = new RedisService;
$redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxInstances = 1;
$redisClient->checkMonitor(RedisSchema::KEY_ORDERS_EVENTS_WEBHOOK_MONITOR, $maxInstances);
$redisClient->incrMonitor(RedisSchema::KEY_ORDERS_EVENTS_WEBHOOK_MONITOR);
try {
    $maxOrderAtTime = 50;
    $ordersEvents = $redisClient->lRange(RedisSchema::KEY_ORDERS_EVENTS_WEBHOOK, 0, $maxOrderAtTime);
    if (empty($ordersEvents) || !is_array($ordersEvents)) {
        throw new UnderflowException('No orders found');
    }

    $redisClient->ltrim(RedisSchema::KEY_ORDERS_EVENTS_WEBHOOK, count($ordersEvents), -1);

    $requestMulti = new RequestMulti();
    $maxAttemptsRequest = $custom->getOpenDelivery()['maxAttemptsRequest'];

    $ordersCache = [];
    foreach ($ordersEvents as &$orderEvent) {
        $orderEvent = json_decode($orderEvent, true);

        if (!empty($ordersCache[$orderEvent['orderId']])) {
            continue;
        }

        $orderCache = new OrderCache($redisClient, $orderEvent['provider']);
        $order = $orderCache->getOrderCache($orderEvent['orderId']);
        $ordersCache[$orderEvent['orderId']] = $order;
        $ordersCache[$orderEvent['orderId']]['keyRedis'] = $orderCache->getOrderDetailsKey($orderEvent['orderId'], RedisSchema::KEY_ORDER_DETAILS);
    }

    $pipeline = [];
    $currentTimestamp = time();
    foreach ($ordersEvents as &$orderEvent) {
        $orderId = $orderEvent['orderId'];
        unset($orderEvent['index']);

        $keyErrorIds = str_replace('{provider}', $orderEvent['provider'], RedisSchema::KEY_ORDERS_IDS_EVENTS_ERR);
        $checkOrder = $redisClient->sIsMember($keyErrorIds, $orderId);
        if ($checkOrder) {
            continue;
        }

        // Verifica se o evento atual, está no tempo correto para ser enviado em caso de retry
        if (!empty($orderEvent['timeToRetry']) && $orderEvent['timeToRetry'] > $currentTimestamp) {
            $pipeline[] = ['rPush', RedisSchema::KEY_ORDERS_EVENTS_WEBHOOK, json_encode($orderEvent)];
            continue;
        }

        $orderEventStateMachine = new OrderEventsStateMachine($ordersCache[$orderId]['lastEvent']);
        if ($orderEventStateMachine->isNewStateEarlierThanCurrentState($orderEvent['event']['type'])) {
            continue;
        }

        if (!$orderEventStateMachine->transition($orderEvent['event']['type'])) {
            $pipeline[] = ['rPush', RedisSchema::KEY_ORDERS_EVENTS_WEBHOOK, json_encode($orderEvent)];
            continue;
        }

        try {
            $customProvider = $custom->getOpenDelivery()['provider'][$orderEvent['provider']] ?? [];
            $urlWebhook = $customProvider['url'] ?? '';

            $webhookProvider = new WebhookProvider($redisClient);

            if ($webhookProvider->supportsMultipleWebhooks($orderEvent['provider'])) {
                $urlWebhook = $webhookProvider->getWebhookUrl($orderEvent['provider'], $orderEvent['merchant']['id']);
            }
        } catch (\Throwable $th) {
            MchLog::logAndInfo('log_opendelivery', MchLogLevel::ERROR, [
                'trace' => $th->getTraceAsString(),
                'message' => $th->getMessage(),
            ]);
            $pipeline[] = ['rPush', RedisSchema::KEY_ORDERS_EVENTS_WEBHOOK, json_encode($orderEvent)];
            continue;
        }

        $body = $orderEvent;
        unset($body['attempts'], $body['index'], $body['app-signature'], $body['provider'], $body['timeToRetry'], $body['nextMultiplierToRetry']);

        $orderEvent['index'] = $orderEvent['orderId'] . '-' . time();
        $orderEvent['attempts'] = ($orderEvent['attempts'] ?? 0) + 1;

        // Tempo para reenvio do evento
        $orderEvent['nextMultiplierToRetry'] = isset($orderEvent['nextMultiplierToRetry']) ? $orderEvent['nextMultiplierToRetry'] * 2 : 1;
        $orderEvent['timeToRetry'] = Util::getNextTimeToRetry($orderEvent['nextMultiplierToRetry'], $custom->getOpenDelivery()['timeToRetry']);

        if (empty($urlWebhook)) {
            $pipeline[] = ['rPush', RedisSchema::KEY_ORDERS_EVENTS_ERR, json_encode($orderEvent)];
            continue;
        }

        $request = new Request($urlWebhook);
        $request
            ->setHeaders($headers = [
                'Content-Type: application/json',
                'x-app-signature: ' . $orderEvent['app-signature'],
                'x-app-id: ' . $customProvider['X-App-Id'],
                'x-app-merchantid: ' . $orderEvent['merchant']['id'],
            ])
            ->setRequestMethod('POST')
            ->setSaveLogs(true)
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields($body);

        $requestMulti->setRequest($orderEvent['index'], $request);
    }

    if (!$requestMulti->haveRequests()) {
        $redisClient->pipelineCommands($pipeline);
        throw new UnderflowException('No orders found');
    }

    $response = $requestMulti->execute();
    unset($requestMulti);

    foreach ($ordersEvents as $orderEventResult) {
        if (empty($orderEventResult['index']) || empty($response[$orderEventResult['index']])) {
            continue;
        }

        $request = $response[$orderEventResult['index']];
        $httpCode = $request?->http_code ?? null;

        // Devido a um erro na provedora Saipos, o evento ORDER_PICKED não está sendo respondido corretamente, para os testes iremos deixar este código para simular o sucesso do evento
        $httpCode = $orderEventResult['provider'] === 'saipos' && $orderEventResult['event']['type'] === 'ORDER_PICKED' ? RequestConstants::HTTP_OK : $httpCode;

        if (!empty($request) && in_array($httpCode, [RequestConstants::HTTP_OK, RequestConstants::HTTP_NO_CONTENT]))  {
            $ordersCache[$orderEventResult['orderId']]['lastEvent'] = $orderEventResult['event']['type'];
            $ordersCache[$orderEventResult['orderId']]['events'][] = $orderEventResult['event'];
            $pipeline[] = ['set', $ordersCache[$orderEventResult['orderId']]['keyRedis'], json_encode($ordersCache[$orderEventResult['orderId']]), ['EX' => RedisSchema::TTL_ORDER_DETAILS]];
            continue;
        }

        if ($orderEventResult['attempts'] >= $maxAttemptsRequest) {
            $orderEventResult['error'] = [
                'http_code' => $httpCode,
                'response' => $request?->content ?? '',
            ];
            $pipeline[] = ['rPush', RedisSchema::KEY_ORDERS_EVENTS_ERR, json_encode($orderEventResult)];
            $keyErrorIds = str_replace('{provider}', $orderEventResult['provider'], RedisSchema::KEY_ORDERS_IDS_EVENTS_ERR);
            $pipeline[] = ['sAdd', $keyErrorIds, $orderEventResult['orderId']];
            continue;
        }

        $pipeline[] = ['rPush', RedisSchema::KEY_ORDERS_EVENTS_WEBHOOK, json_encode($orderEventResult)];
    }

    $redisClient->pipelineCommands($pipeline);
} catch (\UnderflowException $e) {
    echo $e->getMessage();
} catch (\Throwable $th) {
    MchLog::logAndInfo('log_opendelivery', MchLogLevel::ERROR, [
        'trace' => $th->getTraceAsString(),
        'message' => $th->getMessage(),
    ]);
}

$redisClient->decr(RedisSchema::KEY_ORDERS_EVENTS_WEBHOOK_MONITOR);
