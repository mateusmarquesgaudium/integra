<?php

namespace src\Payments\Handlers;

use src\geral\RedisService;
use src\Payments\Entities\PaymentManagerFactory;
use src\Payments\Enums\PaymentsType;

class WebhookHandler {
    private RedisService $redisClient;

    public function __construct(RedisService $redisClient)
    {
        $this->redisClient = $redisClient;
    }

    public function handler(array $requestData): void
    {
        $origemWebhook = $this->getOrigemWebhook($requestData);
        $typeRequest = $this->getWebhookTypeByData($requestData);

        $paymentManager = PaymentManagerFactory::create($origemWebhook, $this->redisClient);
        $paymentManager->handleWebhook($requestData, $typeRequest);

        return;
    }

    private function getWebhookTypeByData(array $requestData): string
    {
        return $requestData['type'] ?? '';
    }

    private function getOrigemWebhook(array $requestData): string
    {
        if (isset($requestData['payload'])) {
            return PaymentsType::PAGZOOP;
        } elseif (isset($requestData['data'])) {
            return PaymentsType::PAGARME;
        }
        return '';
    }
}