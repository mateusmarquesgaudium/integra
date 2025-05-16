<?php

namespace src\Opendelivery\Handlers;

use src\geral\Custom;
use src\geral\CustomException;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use src\geral\RequestValidator;
use src\Opendelivery\Entities\OrderCache;
use src\Opendelivery\Enums\ReasonsCancel;
use src\Opendelivery\Enums\Variables;

class CancelHandler
{
    private RedisService $redisService;
    private string $provider;

    public function __construct(Custom $custom, RedisService $redisService, string $provider)
    {
        $this->redisService = $redisService;
        $this->provider = $provider;
    }

    public function orderCancel(): void
    {
        if (empty($_REQUEST['orderId'])) {
            throw new CustomException('The order id is not null value');
        }

        $orderId = $_REQUEST['orderId'];
        $requestValidator = new RequestValidator(['reason'], 'POST', RequestConstants::CURLOPT_POST_JSON_ENCODE);
        $requestValidator->validateRequest();
        $requestData = $requestValidator->getData();

        if (!ReasonsCancel::isValidReason($requestData['reason'])) {
            throw new CustomException('The provided reason does not match any of the accepted values. Please use one of the following: "' . implode('", "', ReasonsCancel::getAllReasons()) . '"');
        }

        $orderCache = new OrderCache($this->redisService, $this->provider);
        $order = $orderCache->getOrderCache($orderId);
        if (empty($order)) {
            throw new CustomException('The requested resource was not found');
        }

        $orderEvent = [
            'order_status' => Variables::ORDER_TYPE_HIDDEN,
            'provider' => $this->provider,
            'order_id' => $orderId,
            'reason' => $requestData['reason']
        ];

        $orderCache->addEventOrderWebhook($orderEvent);
    }
}