<?php

namespace src\Payments\Enums;

abstract class WebhookEndpoints
{
    const WEBHOOK_POSTBACK = 'postback';
    const WEBHOOK_POSTBACK_RECEBEDOR = 'postbackRecebedor';
    const WEBHOOK_POSTBACK_TRANSFERENCIA = 'postbackTransferencia';
    const WEBHOOK_POSTBACK_TRANSFER = 'postbackTransferencia';
}