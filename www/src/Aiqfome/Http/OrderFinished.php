<?php

namespace src\Aiqfome\Http;

use src\Aiqfome\Enums\RedisSchema;
use src\Aiqfome\Handlers\GetAccessTokenHandler;
use src\Delivery\Entities\OrderFinished as DeliveryOrderFinished;
use src\Delivery\Enums\Provider;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;

class OrderFinished extends DeliveryOrderFinished
{
    private RedisService $redisService;
    private array $custom;

    public function __construct(RedisService $redisService, Custom $custom)
    {
        $this->redisService = $redisService;
        $this->custom = $custom->getParams('aiqfome');
    }

    public function send(array &$event, array &$pipeline): bool
    {
        if (!isset($event['orderId']) || !isset($event['merchantId'])) {
            return false;
        }

        if (isset($event['timeToRetry']) && $event['timeToRetry'] > time()) {
            return false;
        }

        // Verifica se a empresa está na fila de refresh
        $enterpriseInRefreshQueue = $this->redisService->sIsMember(RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH, $event['merchantId']);
        if ($enterpriseInRefreshQueue) {
            return false;
        }

        $getAccessTokenHandler = new GetAccessTokenHandler($this->redisService);
        $accessToken = $getAccessTokenHandler->execute($event['merchantId'], Provider::AIQFOME);

        $postFields = [
            'order_id' => $event['orderId'],
        ];

        $url = $this->custom['url'] . '/orders/mark-as-delivered';
        $request = new Request($url);
        $credentials = $this->custom['credentials'];
        $response = $request
            ->setRequestMethod('POST')
            ->setPostFields($postFields)
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setHeaders([
                'User-Agent: curl/7.68.0',
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'aiq-client-authorization: ' . $credentials['aiq-client-authorization'],
                'aiq-user-agent: ' . $credentials['aiq-user-agent'],
            ])
            ->setSaveLogs(true)
            ->execute();

        if ($response->http_code === RequestConstants::HTTP_UNAUTHORIZED) {
            $event['timeToRetry'] = time() + $this->custom['timeToRetry'];
            $pipeline[] = ['sAdd', RedisSchema::KEY_AUTHENTICATE_AIQFOME_REFRESH, $event['merchantId']];
            return false;
        }

        return in_array($response->http_code, [RequestConstants::HTTP_OK, RequestConstants::HTTP_NO_CONTENT]);
    }
}