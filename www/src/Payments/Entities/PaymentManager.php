<?php

namespace src\Payments\Entities;

abstract class PaymentManager
{
    abstract public function handleWebhook(array $requestData, string $typeRequest): bool;
}