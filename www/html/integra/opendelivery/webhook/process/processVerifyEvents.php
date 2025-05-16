<!--
    Este processo tem como responsabilidade verificar os eventos ocorridos nos pedidos
    e enviar para a fila de notificação dos provedores, sendo assim ele é executado a cada tempo
    e verifica se há eventos na fila 'it:opendel:orders:ev'.

    Com os eventos, ele envia para o txmback uma requisição para obter os detalhes do evento
    e envia para a fila de notificação dos provedores, caso o evento seja de não atendido,
    ele envia para a fila de não atendidos e rejeita o pedido.

    Caso ocorra algum erro, ele é registrado no log 'log_opendelivery'.
 -->

<?php

require_once __DIR__ . '/../../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../../src/autoload.php';

use src\Cache\Enums\CacheKeys;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use src\Opendelivery\Entities\OrderCache;
use src\Opendelivery\Enums\DeliveryStatus;
use src\Opendelivery\Enums\RedisSchema;
use src\Opendelivery\Enums\Variables;

$custom = new Custom;
$redisClient = new RedisService;
$redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxInstances = 1;
$redisClient->checkMonitor(RedisSchema::KEY_ORDERS_EVENTS_MONITOR, $maxInstances);
$redisClient->incrMonitor(RedisSchema::KEY_ORDERS_EVENTS_MONITOR);

try {
    $maxOrdersAtTime = 50;
    $orderEvents = $redisClient->lRange(RedisSchema::KEY_ORDERS_EVENTS, 0, $maxOrdersAtTime);

    if (empty($orderEvents) || !is_array($orderEvents)) {
        throw new UnderflowException('No orders found');
    }

    $redisClient->ltrim(RedisSchema::KEY_ORDERS_EVENTS, count($orderEvents), -1);

    $orders = [];
    $ordersEventsRejected = [];
    foreach ($orderEvents as $orderEvent) {
        $orderEvent = json_decode($orderEvent, true);
        $orderCache = new OrderCache($redisClient, $orderEvent['provider']);

        if (empty($orders[$orderEvent['orderId']])) {
            $order = $orderCache->getOrderCache($orderEvent['orderId']);
            $orders[$orderEvent['orderId']] = $order;
            $orders[$orderEvent['orderId']]['keyRedis'] = $orderCache->getOrderDetailsKey($orderEvent['orderId'], RedisSchema::KEY_ORDER_DETAILS);
        }

        if ($orderEvent['event'] == DeliveryStatus::REJECTED) {
            $ordersEventsRejected[] = $orderEvent;
        }
    }

    $request = new Request($custom->getOpenDelivery()['url_get_orders_tracking']);
    $body = ['orders' => array_keys($orders)];
    $signature = hash_hmac('sha256', json_encode($body), $custom->getOpenDelivery()['signature']);
    $response = $request
        ->setHeaders(['Content-Type: application/json', 'x-integra-signature: ' . $signature])
        ->setRequestMethod('POST')
        ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
        ->setPostFields($body)
        ->setSaveLogs(true)
        ->execute();

    $content = json_decode($response?->content ?? '', true);

    if (empty($content)) {
        $content = [];
    }

    foreach ($ordersEventsRejected as $orderEvent) {
        $orderDetail = $content[$orderEvent['orderId']] ?? null;
        if (!empty($orderDetail)) {
            continue;
        }

        $order = $orders[$orderEvent['orderId']];
        if (empty($order) || $order['lastEvent'] != DeliveryStatus::PENDING) {
            continue;
        }

        $providerMerchantKey = str_replace(['{provider}', '{merchantId}'], [$orderEvent['provider'], $order['merchant']['id']], CacheKeys::PROVIDER_MERCHANTS_KEY);
        $enterpriseIntegrationId = $redisClient->hGet($providerMerchantKey, 'enterpriseIntegrationId');

        if (empty($enterpriseIntegrationId)) {
            continue;
        }

        $integrationKey = str_replace('{integrationId}', $enterpriseIntegrationId, CacheKeys::ENTERPRISE_INTEGRATION_KEY);
        $clientSecret = $redisClient->hGet($integrationKey, 'client_secret');

        if (empty($clientSecret)) {
            continue;
        }

        $content[$orderEvent['orderId']] = [
            'deliveryId' => $order['deliveryId'],
            'orderId' => $order['orderId'],
            'orderDisplayId' => $order['orderDisplayId'],
            'merchant' => $order['merchant'],
            'customerName' => $order['customerName'],
            'client_secret' => $clientSecret,
        ];
    }

    $pipeline = [];
    foreach ($orderEvents as $orderEvent) {
        $orderEvent = json_decode($orderEvent, true);
        $orderDetail = $content[$orderEvent['orderId']] ?? null;

        if (empty($orderDetail)) {
            continue;
        }

        $orderDetail['event'] = [
            'type' => $orderEvent['event'],
            'datetime' => $orderEvent['datetime']
        ];

        if ($orders[$orderEvent['orderId']]['lastEvent'] == DeliveryStatus::PENDING && $orderEvent['event'] == DeliveryStatus::CANCELLED) {
            $orderDetail['event']['type'] = DeliveryStatus::REJECTED;
            $orderDetail['event']['rejectionInfo'] = [
                'reason' => Variables::NO_DELIVERYPERSON_AVAILABLE,
                'metadata' => [
                    'nextAvailableVehicle' => Variables::TIME_TO_NEXT_AVAILABLE_DELIVERY_PERSON
                ],
            ];
        }

        if (!in_array($orderEvent['event'], [DeliveryStatus::CANCELLED, DeliveryStatus::PENDING, DeliveryStatus::NOT_CALLED, DeliveryStatus::REJECTED]) && empty($orderDetail['deliveryPerson'])) {
            $pipeline[] = ['rPush', RedisSchema::KEY_ORDERS_NOT_DELIVERY_PERSON, json_encode([
                'event' => $orderEvent['event'],
                'orderId' => $orderEvent['orderId'],
            ])];
            continue;
        }

        if ($orderEvent['event'] == DeliveryStatus::REJECTED) {
            $orderDetail['event']['rejectionInfo'] = [
                'reason' => Variables::NO_DELIVERYPERSON_AVAILABLE,
                'metadata' => [
                    'nextAvailableVehicle' => Variables::TIME_TO_NEXT_AVAILABLE_DELIVERY_PERSON
                ],
            ];
        }

        // Adiciona o pedido à uma nova fila quando status da OS for não atendida
        if ($orderEvent['event'] == DeliveryStatus::NOT_CALLED) {
            $order = $orders[$orderEvent['orderId']];
            $keyRedis = $order['keyRedis'];
            $currentTime = time();
            $expirationTime = $currentTime + (Variables::TIME_TO_EXPIRATION_UNATTENDED * 60);

            $prefix = $orderEvent['orderId'] . '-' . $currentTime;
            $key = str_replace('{prefix}', $prefix, RedisSchema::KEY_ORDERS_EVENTS_UNATTENDED_STORE);
            $order['keyUnattended'] = $key;

            $orderDetail['event']['type'] = DeliveryStatus::REJECTED;
            $orderDetail['event']['rejectionInfo'] = [
                'reason' => Variables::NO_DELIVERYPERSON_AVAILABLE,
                'metadata' => [
                    'nextAvailableVehicle' => Variables::TIME_TO_NEXT_AVAILABLE_DELIVERY_PERSON
                ],
            ];
            $clientSecret = $orderDetail['client_secret'];
            unset($orderDetail['client_secret'], $orderDetail['pedido_id'], $orderDetail['solicitacao_id']);
            $orderDetail['app-signature'] = hash_hmac('sha256', json_encode($orderDetail), $clientSecret);
            $orderDetail['provider'] = $orderEvent['provider'];

            $pipeline[] = ['set', $key, json_encode($orderDetail)];
            $pipeline[] = ['expire', $key, Variables::TIME_TO_EXPIRATION_UNATTENDED * 60 * 5];
            $pipeline[] = ['zAdd', RedisSchema::KEY_ORDERS_EVENTS_UNATTENDED, $expirationTime, $key];

            unset($order['keyRedis']);
            $pipeline[] = ['set', $keyRedis, json_encode($order), ['EX' => RedisSchema::TTL_ORDER_DETAILS]];
            continue;
        }

        $clientSecret = $orderDetail['client_secret'];
        unset($orderDetail['client_secret'], $orderDetail['pedido_id'], $orderDetail['solicitacao_id']);
        $orderDetail['app-signature'] = hash_hmac('sha256', json_encode($orderDetail), $clientSecret);
        $orderDetail['provider'] = $orderEvent['provider'];
        $pipeline[] = ['rPush', RedisSchema::KEY_ORDERS_EVENTS_WEBHOOK, json_encode($orderDetail)];
    }

    $redisClient->pipelineCommands($pipeline);
} catch (\UnderflowException $e) {
    echo $e->getMessage();
} catch (\Throwable $t) {
    MchLog::logAndInfo('log_opendelivery', MchLogLevel::ERROR, [
        'trace' => $t->getTraceAsString(),
        'message' => $t->getMessage(),
    ]);
}

$redisClient->decr(RedisSchema::KEY_ORDERS_EVENTS_MONITOR);
