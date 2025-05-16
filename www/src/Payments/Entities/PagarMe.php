<?php

namespace src\Payments\Entities;

use src\geral\RedisService;
use src\Payments\Enums\EventWebhookType;
use src\Payments\Enums\WebhookEndpoints;
use src\PagarMe\Enums\RedisSchema;

class PagarMe extends PaymentManager
{
    private RedisService $redisClient;

    public function __construct(RedisService $redisClient)
    {
        $this->redisClient = $redisClient;
    }

    public function handleWebhook(array $requestData, string $typeRequest): bool
    {
        switch ($typeRequest) {
            case EventWebhookType::PAGARME_ORDER_CANCELED:
            case EventWebhookType::PAGARME_ORDER_CREATED:
            case EventWebhookType::PAGARME_ORDER_PAID:
            case EventWebhookType::PAGARME_ORDER_PAYMENT_FAILED:
            case EventWebhookType::PAGARME_ORDER_UPDATED:
                $data = json_encode(['url' => WebhookEndpoints::WEBHOOK_POSTBACK, 'post' => $requestData]);
                $this->redisClient->rPush(RedisSchema::LIST_PAGARME_EVENTS_WEBHOOK, $data);
                break;
            case EventWebhookType::PAGARME_RECIPIENT_CREATED:
            case EventWebhookType::PAGARME_RECIPIENT_DELETED:
            case EventWebhookType::PAGARME_RECIPIENT_UPDATED:
                $data = json_encode(['url' => WebhookEndpoints::WEBHOOK_POSTBACK_RECEBEDOR, 'post' => $requestData]);
                $this->redisClient->rPush(RedisSchema::LIST_PAGARME_EVENTS_WEBHOOK, $data);
                break;
            case EventWebhookType::PAGARME_TRANSFER_CANCELED:
            case EventWebhookType::PAGARME_TRANSFER_CREATED:
            case EventWebhookType::PAGARME_TRANSFER_FAILED:
            case EventWebhookType::PAGARME_TRANSFER_PAID:
            case EventWebhookType::PAGARME_TRANSFER_PENDING:
            case EventWebhookType::PAGARME_TRANSFER_PROCESSING:
                $data = json_encode(['url' => WebhookEndpoints::WEBHOOK_POSTBACK_TRANSFERENCIA, 'post' => $requestData]);
                $this->redisClient->rPush(RedisSchema::LIST_PAGARME_EVENTS_WEBHOOK, $data);
                break;
            default:
                return false;
                break;
        }
        return true;
    }
}