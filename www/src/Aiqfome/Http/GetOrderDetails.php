<?php

namespace src\Aiqfome\Http;

use src\Aiqfome\Enums\RedisSchema;
use src\Aiqfome\Handlers\CheckOrderDetailsHandler;
use src\Aiqfome\Handlers\CreateRequestsOrdersHandler;
use src\geral\Custom;
use src\geral\RedisService;
use src\geral\RequestMulti;
use UnderflowException;

class GetOrderDetails
{
    private Custom $custom;
    private array $customAIQFome;
    private RedisService $redisService;

    public function __construct(Custom $custom, RedisService $redisService)
    {
        $this->custom = $custom;
        $this->customAIQFome = $custom->getParams('aiqfome');
        $this->redisService = $redisService;
    }

    public function execute(): void
    {
        $ordersEvents = $this->redisService->lRange(RedisSchema::KEY_LIST_ORDERS_EVENTS, 0, $this->customAIQFome['maxOrdersAtATime']);
        if (empty($ordersEvents) || !is_array($ordersEvents)) {
            throw new UnderflowException('No orders events found');
        }

        $this->redisService->lTrim(RedisSchema::KEY_LIST_ORDERS_EVENTS, count($ordersEvents), -1);

        $pipeline = [];
        $createRequestsOrdersHandler = new CreateRequestsOrdersHandler($this->custom, $this->redisService);
        $requests = $createRequestsOrdersHandler->execute($this->customAIQFome['url'] . '/orders/{order_id}', $ordersEvents);

        $requestMulti = new RequestMulti($requests, $this->custom);
        $getOrderDetailsHandler = new CheckOrderDetailsHandler($requestMulti, $this->custom);
        $getOrderDetailsHandler->execute($ordersEvents, $pipeline);

        $this->redisService->pipelineCommands($pipeline);
    }
}