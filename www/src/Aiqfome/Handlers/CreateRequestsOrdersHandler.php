<?php

namespace src\Aiqfome\Handlers;

use Exception;
use src\Aiqfome\Enums\RedisSchema;
use src\Delivery\Enums\Provider;
use src\geral\Custom;
use src\geral\RedisService;
use src\geral\Request;

class CreateRequestsOrdersHandler
{
    private array $customAIQFome;
    private RedisService $redisService;

    public function __construct(Custom $custom, RedisService $redisService)
    {
        $this->customAIQFome = $custom->getParams('aiqfome');
        $this->redisService = $redisService;
    }

    public function execute(string $url, array &$orders): array
    {
        $pipeline = [];
        $requests = [];
        $currentTime = strtotime('now');

        $getAccessTokenHandler = new GetAccessTokenHandler($this->redisService);
        $merchantsNotAuthenticated = [];

        foreach ($orders as &$order) {
            $order = json_decode($order, true);
            if (empty($order) || empty($order['provider']) || empty($order['order_id'])) {
                continue;
            }

            if (!empty($order['nextTimeToRetry']) && $order['nextTimeToRetry'] > $currentTime) {
                $pipeline[] = ['rPush', RedisSchema::KEY_LIST_ORDERS_EVENTS, json_encode($order)];
                continue;
            }

            if (in_array($order['merchant_id'], $merchantsNotAuthenticated)) {
                $pipeline[] = ['rPush', RedisSchema::KEY_LIST_ORDERS_EVENTS, json_encode($order)];
                continue;
            }

            if ($this->redisService->sIsMember(RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH, $order['merchant_id'])) {
                $pipeline[] = ['rPush', RedisSchema::KEY_LIST_ORDERS_EVENTS, json_encode($order)];
                continue;
            }

            if ($this->redisService->sIsMember(RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS, $order['merchant_id'])) {
                $pipeline[] = ['rPush', RedisSchema::KEY_LIST_ORDERS_ERR_EVENTS, json_encode($order)];
                continue;
            }

            if (isset($order['attempts']) && $order['attempts'] >= $this->customAIQFome['maxAttemptsRequest']) {
                $pipeline[] = ['rPush', RedisSchema::KEY_LIST_ORDERS_ERR_EVENTS, json_encode($order)];
                continue;
            }

            $accessToken = null;
            try {
                $accessToken = $getAccessTokenHandler->execute($order['merchant_id'], Provider::AIQFOME);
            } catch (Exception $e) {
                $order['attempts'] = ($order['attempts'] ?? 0) + 1;
                $order['nextTimeToRetry'] = $currentTime + $this->customAIQFome['timeToRetry'];

                $pipeline[] = ['rPush', RedisSchema::KEY_LIST_ORDERS_EVENTS, json_encode($order)];
                $pipeline[] = ['sAdd', RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH, $order['merchant_id']];
                $merchantsNotAuthenticated[] = $order['merchant_id'];
                continue;
            }

            $request = new Request(str_replace('{order_id}', $order['order_id'], $url));
            $request
                ->setRequestMethod('GET')
                ->setHeaders([
                    'User-Agent: curl/7.68.0',
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                    'aiq-client-authorization: ' . $this->customAIQFome['credentials']['aiq-client-authorization'],
                    'aiq-user-agent: ' . $this->customAIQFome['credentials']['aiq-user-agent'],
                ]);

            $requests[$order['order_id']] = $request;
            $order['attempts'] = ($order['attempts'] ?? 0) + 1;
            $order['nextTimeToRetry'] = $currentTime + $this->customAIQFome['timeToRetry'];
        }

        $this->redisService->pipelineCommands($pipeline);
        return $requests;
    }
}