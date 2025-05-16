<?php

namespace src\Payments\Enums;

abstract class EventWebhookType
{
    const PAGARME_ORDER_CANCELED = 'order.canceled';
    const PAGARME_ORDER_CLOSED = 'order.closed';
    const PAGARME_ORDER_CREATED = 'order.created';
    const PAGARME_ORDER_PAID = 'order.paid';
    const PAGARME_ORDER_PAYMENT_FAILED = 'order.payment_failed';
    const PAGARME_ORDER_UPDATED = 'order.updated';

    const PAGARME_RECIPIENT_CREATED = 'recipient.created';
    const PAGARME_RECIPIENT_DELETED = 'recipient.deleted';
    const PAGARME_RECIPIENT_UPDATED = 'recipient.updated';

    const PAGARME_TRANSFER_CANCELED = 'transfer.canceled';
    const PAGARME_TRANSFER_CREATED = 'transfer.created';
    const PAGARME_TRANSFER_FAILED = 'transfer.failed';
    const PAGARME_TRANSFER_PAID = 'transfer.paid';
    const PAGARME_TRANSFER_PENDING = 'transfer.pending';
    const PAGARME_TRANSFER_PROCESSING = 'transfer.processing';

    const PAGZOOP_SELLER_UPDATED = 'seller.updated';
    const PAGZOOP_SELLER_ACTIVATED = 'seller.activated';
    const PAGZOOP_SELLER_ENABLED = 'seller.enabled';
    const PAGZOOP_SELLER_DELETED = 'seller.deleted';
    const PAGZOOP_SELLER_DENIED = 'seller.denied';

    const PAGZOOP_TRANSACTION_CANCELED = 'transaction.canceled';
    const PAGZOOP_TRANSACTION_SUCCEEDED = 'transaction.succeeded';
    const PAGZOOP_TRANSACTION_FAILED = 'transaction.failed';
    const PAGZOOP_TRANSACTION_REVERSED = 'transaction.reversed';
    const PAGZOOP_TRANSACTION_UPDATED = 'transaction.updated';
    const PAGZOOP_TRANSACTION_DISPUTED = 'transaction.disputed';
    const PAGZOOP_TRANSACTION_CHARGED_BACK = 'transaction.charged_back';

    const PAGZOOP_TRANSFER_CREATED = 'transfer.created';
    const PAGZOOP_TRANSFER_CONFIRMED = 'transfer.confirmed';
    const PAGZOOP_TRANSFER_FAILED = 'transfer.failed';
    const PAGZOOP_TRANSFER_CANCELED = 'transfer.canceled';
    const PAGZOOP_TRANSFER_SUCCEEDED = 'transfer.succeeded';
}