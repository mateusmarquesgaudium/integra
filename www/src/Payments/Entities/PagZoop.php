<?php

namespace src\Payments\Entities;

use src\geral\RedisService;
use src\Payments\Enums\EventWebhookType;
use src\Payments\Enums\WebhookEndpoints;
use src\PagZoop\Enums\RedisSchema;

class PagZoop extends PaymentManager
{
    private RedisService $redisClient;

    public function __construct(RedisService $redisClient)
    {
        $this->redisClient = $redisClient;
    }

    public function handleWebhook(array $requestData, string $typeRequest): bool
    {
        switch ($typeRequest) {
            case EventWebhookType::PAGZOOP_SELLER_UPDATED:
            case EventWebhookType::PAGZOOP_SELLER_ACTIVATED:
            case EventWebhookType::PAGZOOP_SELLER_ENABLED:
            case EventWebhookType::PAGZOOP_SELLER_DELETED:
            case EventWebhookType::PAGZOOP_SELLER_DENIED:
                $data = json_encode(['url' => WebhookEndpoints::WEBHOOK_POSTBACK_RECEBEDOR, 'post' => $requestData]);
                $this->redisClient->rPush(RedisSchema::LIST_PAGZOOP_EVENTS_WEBHOOK, $data);
                break;
            case EventWebhookType::PAGZOOP_TRANSACTION_CANCELED:
            case EventWebhookType::PAGZOOP_TRANSACTION_SUCCEEDED:
            case EventWebhookType::PAGZOOP_TRANSACTION_FAILED:
            case EventWebhookType::PAGZOOP_TRANSACTION_REVERSED:
            case EventWebhookType::PAGZOOP_TRANSACTION_UPDATED:
            case EventWebhookType::PAGZOOP_TRANSACTION_DISPUTED:
            case EventWebhookType::PAGZOOP_TRANSACTION_CHARGED_BACK:
                $data = json_encode(['url' => WebhookEndpoints::WEBHOOK_POSTBACK, 'post' => $requestData]);
                $this->redisClient->rPush(RedisSchema::LIST_PAGZOOP_EVENTS_WEBHOOK, $data);
                break;
            case EventWebhookType::PAGZOOP_TRANSFER_CREATED:
            case EventWebhookType::PAGZOOP_TRANSFER_CONFIRMED:
            case EventWebhookType::PAGZOOP_TRANSFER_FAILED:
            case EventWebhookType::PAGZOOP_TRANSFER_CANCELED:
            case EventWebhookType::PAGZOOP_TRANSFER_SUCCEEDED:
                $data = json_encode(['url' => WebhookEndpoints::WEBHOOK_POSTBACK_TRANSFER, 'post' => $requestData]);
                $this->redisClient->rPush(RedisSchema::LIST_PAGZOOP_EVENTS_WEBHOOK, $data);
                break;
            default:
                return false;
                break;
        }
        return true;
    }
}