<?php

namespace src\Payments\Entities;

use src\geral\RedisService;
use src\Payments\Entities\PaymentManager;
use src\Payments\Entities\PagarMe;
use src\Payments\Entities\PagZoop;
use src\Payments\Enums\PaymentsType;

class PaymentManagerFactory
{
    public static function create(string $entity, RedisService $redisService): PaymentManager
    {
        switch ($entity) {
            case PaymentsType::PAGARME:
                return new PagarMe($redisService);
            case PaymentsType::PAGZOOP:
                return new PagZoop($redisService);
            default:
                throw new \Exception('Payment not found');
        }
    }
}