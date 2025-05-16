<?php

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

use src\Aiqfome\Enums\Constants;
use src\Aiqfome\Enums\RedisSchema;
use src\Aiqfome\Http\AuthenticateToken;
use src\Delivery\Enums\Provider;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\JWTHandler;
use src\geral\RedisService;
use src\geral\Request;
use src\geral\RequestMulti;
use src\geral\RequestValidator;
use src\geral\Util;

try {
    $custom = new Custom;
    $redisService = new RedisService;
    $redisService->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

    $validator = new RequestValidator(['client_username', 'empresa_id', 'client_password'], 'PUT', RequestConstants::CURLOPT_POST_JSON_ENCODE);
    $validator->validateRequest();
    $requestData = $validator->getData();

    $request = new Request($custom->getParams('aiqfome')['url'] . '/auth/token');
    $authenticateHandler = new AuthenticateToken($request, $custom, $redisService);
    $authenticateHandler->handle($requestData['client_username'], $requestData['client_password'], $requestData['empresa_id']);

    $key = str_replace('{enterprise_id}', $requestData['empresa_id'], RedisSchema::KEY_AUTHENTICATE_AIQFOME);
    $accessToken = $redisService->hGet($key, 'access_token');
    if (!$accessToken) {
        throw new \Exception('Token de autenticação não encontrado.');
    }

    $request = new Request($custom->getParams('aiqfome')['url'] . '/store');
    $credentials = $custom->getParams('aiqfome')['credentials'];
    $response = $request
                ->setRequestMethod('GET')
                ->setHeaders([
                    'User-Agent: curl/7.68.0',
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                    'aiq-client-authorization: ' . $credentials['aiq-client-authorization'],
                    'aiq-user-agent: ' . $credentials['aiq-user-agent'],
                ])
                ->execute();

    if ($response->http_code !== RequestConstants::HTTP_OK || empty($response->content)) {
        throw new \Exception('Erro ao buscar lojas.');
    }

    $stores = json_decode($response->content, true);
    if (empty($stores['data'])) {
        throw new \Exception('Nenhuma loja encontrada.');
    }

    $requests = [];
    $secretsKey = [];
    foreach ($stores['data'] as $store) {
        $request = new Request($custom->getParams('aiqfome')['url'] . '/store/' . $store['id'] . '/webhooks');

        $jwtHandler = new JWTHandler($custom->getParams('delivery')['jwt_secret']);
        $secretsKey[$store['id']] = $jwtHandler->encode(
            ['provider' => Provider::AIQFOME, 'merchant_id' => $store['id']],
        );

        $request
            ->setRequestMethod('GET')
            ->setHeaders([
                'User-Agent: curl/7.68.0',
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'aiq-client-authorization: ' . $credentials['aiq-client-authorization'],
                'aiq-user-agent: ' . $credentials['aiq-user-agent'],
            ]);

        $requests[$store['id']] = $request;
    }

    $dataSend = [];
    $responses = (new RequestMulti($requests))->execute();
    foreach ($stores['data'] as $store) {
        $response = $responses[$store['id']];
        if ($response->http_code !== RequestConstants::HTTP_OK) {
            continue;
        }

        $response = json_decode($response->content, true);
        if (empty($response['data'])) {
            continue;
        }

        $dataSend[] = [
            'store_id' => $store['id'],
            'name' => $store['name'],
            'webhooks' => $response['data'],
            'secret_key' => $secretsKey[$store['id']]
        ];
    }

    if (!empty($dataSend)) {
        Util::sendJson([
            'status' => true,
            'data' => $dataSend
        ]);
    }

    $requests = [];
    $secretsKey = [];
    foreach ($stores['data'] as $store) {
        $request = new Request($custom->getParams('aiqfome')['url'] . '/store/' . $store['id'] . '/webhooks');

        $jwtHandler = new JWTHandler($custom->getParams('delivery')['jwt_secret']);
        $secretKey = $jwtHandler->encode(
            ['provider' => Provider::AIQFOME, 'merchant_id' => $store['id']],
        );

        $secretsKey[$store['id']] = $secretKey;

        $url = $custom->getParams('delivery')['urlWebhookEvents'];
        $data = [
            'webhooks' => [
                ['url' => $url, 'secret_key' => $secretKey, 'webhook_event_id' => Constants::CANCEL_ORDER_WEBHOOK_ID],
                ['url' => $url, 'secret_key' => $secretKey, 'webhook_event_id' => Constants::READ_ORDER_WEBHOOK_ID],
                ['url' => $url, 'secret_key' => $secretKey, 'webhook_event_id' => Constants::READY_ORDER_WEBHOOK_ID]
            ]
        ];

        $request
            ->setRequestMethod('POST')
            ->setHeaders([
                'User-Agent: curl/7.68.0',
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'aiq-client-authorization: ' . $credentials['aiq-client-authorization'],
                'aiq-user-agent: ' . $credentials['aiq-user-agent'],
            ])
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields($data);

        $requests[$store['id']] = $request;
    }

    $requestMulti = new RequestMulti($requests);
    $responses = $requestMulti->execute();

    $errosWebhooks = [];
    foreach ($stores['data'] as $store) {
        $response = $responses[$store['id']];
        if ($response->http_code !== RequestConstants::HTTP_OK) {
            $errosWebhooks[] = [
                'store_id' => $store['id'],
                'error' => $response->content
            ];
            continue;
        }

        $response = json_decode($response->content, true);
        $dataSend[] = [
            'store_id' => $store['id'],
            'name' => $store['name'],
            'webhooks' => $response['data'],
            'secret_key' => $secretsKey[$store['id']]
        ];
    }

    if (!empty($errosWebhooks)) {
        throw new \Exception('Erro ao adicionar webhooks nas lojas.');
    }

    if (empty($dataSend)) {
        throw new \Exception('Erro ao adicionar webhooks nas lojas.');
    }

    Util::sendJson([
        'status' => true,
        'data' => $dataSend
    ]);
} catch (\Throwable $th) {
    MchLog::logAndInfo('log_aiqfome', MchLogLevel::ERROR, [
        'message' => $th->getMessage(),
        'empresa_id' => $requestData['empresa_id'] ?? null,
        'trace' => $th->getTraceAsString()
    ]);

    Util::sendJson([
        'status' => false,
        'message' => $th->getMessage(),
    ]);
}
