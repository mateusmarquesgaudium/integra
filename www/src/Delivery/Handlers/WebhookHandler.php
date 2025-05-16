<?php

namespace src\Delivery\Handlers;

use src\AnotaAi\Entities\OrderCache as AnotaAiOrderCache;
use src\AnotaAi\Infrastructure\Logging\EventLogger as AnotaAiEventLogger;
use src\Cache\Entities\EntityManagerFactory;
use src\Delivery\Entities\EventOrderCache;
use src\Delivery\Enums\EventWebhookType;
use src\Aiqfome\Enums\EventWebhookType as AiqfomeEventWebhookType;
use src\Delivery\Enums\Provider;
use src\Delivery\ProviderManagerRequest;
use src\DeliveryDireto\Entities\OrderCache as DeliveryDiretoOrderCache;
use src\Neemo\Entities\OrderCache as NeemoOrderCache;
use src\Aiqfome\Entities\OrderCache as AiqfomeOrderCache;
use src\Cache\Entities\CacheValidator;
use src\geral\RedisService;
use src\ifood\Entities\OrderCache as IfoodOrderCache;
use src\ifood\Entities\EventsOrderCache as IfoodEventsOrderCache;
use src\ifood\Infrastructure\Logging\IfoodEventLogger;
use src\Opendelivery\Entities\OrderEventDelivery;

class WebhookHandler {

    private ProviderManagerRequest $providerManagerRequest;
    private RedisService $redisClient;

    public function __construct(ProviderManagerRequest $providerManagerRequest, RedisService $redisClient)
    {
        $this->redisClient = $redisClient;
        $this->providerManagerRequest = $providerManagerRequest;
    }

    public function handleWebhook(array $requestData): void
    {
        $eventWebhook = $this->getWebhookTypeByData($requestData);
        switch ($eventWebhook) {
            case EventWebhookType::IFOOD_CONFIRMED:
            case EventWebhookType::IFOOD_DISPATCHED:
            case EventWebhookType::IFOOD_CANCELLED:
                if (!isset($requestData['merchantId'], $requestData['orderId'])) {
                    return;
                }
                IfoodEventLogger::logEvent($this->redisClient, ['eventData' => $requestData]);
                $orderCache = new IfoodOrderCache($this->redisClient);
                $orderCache->addEventOrderWebhook($requestData['merchantId'], $requestData['orderId'], $eventWebhook);
                break;
            case EventWebhookType::DELIVERY_DIRETO_APPROVED:
            case EventWebhookType::DELIVERY_DIRETO_HIDDEN:
            case EventWebhookType::DELIVERY_DIRETO_IN_TRANSIT:
            case EventWebhookType::DELIVERY_DIRETO_DONE:
                $orderCache = new DeliveryDiretoOrderCache($this->redisClient);
                $orderCache->addEventOrderWebhook($this->providerManagerRequest->dataProvider['merchant_id'], $requestData['ordersId'], $requestData['orderStatus']);
                break;
            case EventWebhookType::ANOTAAI_CREATED:
            case EventWebhookType::ANOTAAI_CANCELED:
                AnotaAiEventLogger::logEvent($this->redisClient, ['eventData' => $requestData, 'webhookType' => $eventWebhook]);
                $requestData['webhookType'] = $eventWebhook;
                $orderCache = new AnotaAiOrderCache($this->redisClient);
                $orderCache->addEventOrderWebhook($requestData['merchant']['id'], $requestData['id'], $requestData);
                break;
            case EventWebhookType::NEEMO_CREATED:
                $orderCache = new NeemoOrderCache($this->redisClient);
                $orderCache->addEventOrderWebhook($requestData['account_access_token'], $requestData['order_id']);
                break;
            case EventWebhookType::IFOOD_OTHER:
            case EventWebhookType::IFOOD_KEEPALIVE:
            case EventWebhookType::DELIVERY_DIRETO_REJECTED:
                break;
            case EventWebhookType::IFOOD_CONCLUDED:
                if (!isset($requestData['orderId'])) {
                    return;
                }
                IfoodEventLogger::logEvent($this->redisClient, ['eventData' => $requestData]);
                $orderCache = new IfoodOrderCache($this->redisClient);
                $orderCache->clearOrderWebhookCache($requestData['orderId']);
                break;
            case EventWebhookType::MACHINE_DELIVERY_IN_TRANSIT:
                $eventOrderCache = new EventOrderCache($this->redisClient);
                $eventOrderCache->addEventInTransit($requestData);
                break;
            case EventWebhookType::MACHINE_DELIVERY_FINISHED:
                $eventOrderCache = new EventOrderCache($this->redisClient);
                $eventOrderCache->addEventFinished($requestData);
                break;
            case EventWebhookType::MACHINE_INSERT_ENTITY:
                $entityManager = EntityManagerFactory::create($requestData['model'], $this->redisClient);
                $entityManager->save($requestData);
                break;
            case EventWebhookType::MACHINE_UPDATE_ENTITY:
                $entityManager = EntityManagerFactory::create($requestData['model'], $this->redisClient);
                $entityManager->update($requestData);
                break;
            case EventWebhookType::MACHINE_DELETE_ENTITY:
                $entityManager = EntityManagerFactory::create($requestData['model'], $this->redisClient);
                $entityManager->delete($requestData);
                break;
            case EventWebhookType::MACHINE_ASSIGN_DRIVER:
            case EventWebhookType::MACHINE_GOING_TO_ORIGIN:
            case EventWebhookType::MACHINE_ARRIVED_AT_ORIGIN:
            case EventWebhookType::MACHINE_DISPATCHED:
            case EventWebhookType::MACHINE_ARRIVED_AT_DESTINATION:
                $eventOrderCache = new IfoodEventsOrderCache($this->redisClient);
                $eventOrderCache->addEventInProcess($requestData);
                break;
            case EventWebhookType::AIQFOME_READ_ORDER:
                $cacheValidator = new CacheValidator($this->redisClient, Provider::AIQFOME, $requestData['store_id']);
                $eventOrderCache = new AiqfomeOrderCache($this->redisClient, $cacheValidator);
                $eventOrderCache->addEventOrderReadWebhook($requestData['store_id'], $requestData['data']['order_id']);
                break;
            case EventWebhookType::AIQFOME_READY_ORDER:
                $cacheValidator = new CacheValidator($this->redisClient, Provider::AIQFOME, $requestData['store_id']);
                $eventOrderCache = new AiqfomeOrderCache($this->redisClient, $cacheValidator);
                $eventOrderCache->addEventOrderReadyWebhook($requestData['store_id'], $requestData['data']['order_id']);
                break;
            case EventWebhookType::AIQFOME_CANCEL_ORDER:
                $cacheValidator = new CacheValidator($this->redisClient, Provider::AIQFOME, $requestData['store_id']);
                $eventOrderCache = new AiqfomeOrderCache($this->redisClient, $cacheValidator);
                $eventOrderCache->addEventOrderCancelWebhook($requestData['store_id'], $requestData['data']['order_id']);
            case EventWebhookType::MACHINE_SEND_OPENDELIVERY_EVENT:
                $orderEventDelivery = new OrderEventDelivery($this->redisClient);
                $orderEventDelivery->addOrderEventToCache($requestData);
                break;
            default:
                throw new \InvalidArgumentException('Event webhook is not found');
                break;
        }
    }

    private function getWebhookTypeByData(array $requestData): ?string
    {
        if ($this->providerManagerRequest->provider === Provider::IFOOD) {
            $webhookType = strtoupper($this->providerManagerRequest->provider) . "_{$requestData['fullCode']}";
            if (!defined("src\\Delivery\\Enums\\EventWebhookType::$webhookType")) {
                return EventWebhookType::IFOOD_OTHER;
            }
            return $webhookType;
        } elseif ($this->providerManagerRequest->provider === Provider::DELIVERY_DIRETO && isset($requestData['orderStatus'])) {
            return strtoupper($this->providerManagerRequest->provider) . "_{$requestData['orderStatus']}";
        } elseif ($this->providerManagerRequest->provider === Provider::MACHINE && isset($requestData['webhookType'])) {
            return strtoupper($this->providerManagerRequest->provider) . "_{$requestData['webhookType']}";
        } elseif ($this->providerManagerRequest->provider === Provider::ANOTAAI) {
            if (isset($requestData['id'], $requestData['canceled']) && !isset($requestData['check']) && $requestData['canceled']) {
                return EventWebhookType::ANOTAAI_CANCELED;
            } elseif (isset($requestData['id'], $requestData['check'], $requestData['type'])) {
                return EventWebhookType::ANOTAAI_CREATED;
            }
        } elseif ($this->providerManagerRequest->provider === Provider::NEEMO) {
            return EventWebhookType::NEEMO_CREATED;
        } elseif ($this->providerManagerRequest->provider === Provider::AIQFOME &&
            isset($requestData['event'], $requestData['store_id'], $requestData['data']['order_id']) &&
            (!isset($requestData['data']['is_aiqentrega_delivery']) || !$requestData['data']['is_aiqentrega_delivery'])
        ) {
            switch (strtoupper($requestData['event'])) {
                case AiqfomeEventWebhookType::READ_ORDER:
                    return EventWebhookType::AIQFOME_READ_ORDER;
                case AiqfomeEventWebhookType::READY_ORDER:
                    return EventWebhookType::AIQFOME_READY_ORDER;
                case AiqfomeEventWebhookType::CANCEL_ORDER:
                    return EventWebhookType::AIQFOME_CANCEL_ORDER;
            }
        }
        return null;
    }
}