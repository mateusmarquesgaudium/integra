<?php

namespace src\Opendelivery\Enums;

abstract class RedisSchema
{
    const KEY_ADD_LOG_PAYLOAD = 'it:opendel:log:payload';
    const KEY_MERCHANTS_PROVIDER = 'it:opendel:cdt:{provider}';
    const KEY_AUTHENTICATE_ORDER_SERVICE = 'it:opendel:cdt:';
    const KEY_ORDERS_EVENTS = 'it:opendel:ord:ev';
    const KEY_ORDERS_EVENTS_WEBHOOK = 'it:opendel:ord:hook:ev';
    const KEY_ORDERS_EVENTS_ERR = 'it:opendel:ord:err';
    const KEY_ORDERS_EVENTS_MONITOR = 'it:opendel:ord:mt';
    const KEY_ORDERS_EVENTS_WEBHOOK_MONITOR = 'it:opendel:ord:hook:mt';
    const KEY_ORDERS_EVENTS_UNATTENDED_STORE = 'it:opendel:ord:un:{prefix}';
    const KEY_ORDERS_EVENTS_UNATTENDED_MONITOR = 'it:opendel:ord:un:mt';
    const KEY_ORDERS_EVENTS_UNATTENDED = 'it:opendel:ord:un';
    const KEY_CREDENTIALS_PROVIDER = 'it:opendel:cdt:{provider}:crd:{empresa_id}:{client_id}';
    const KEY_ORDERS_IDS_EVENTS_ERR = 'it:opendel:ord:{provider}:err:ids';
    const KEY_ORDERS_NOT_DELIVERY_PERSON = 'it:opendel:ord:err:dlvp';
    const KEY_SUPPORTS_MULTIPLE_WEBHOOKS = 'it:opendel:sup:mul:wh';

    // Keys
    const KEY_ACCESS_TOKEN_PROVIDER = 'it:opendel:cdt:{provider}:at';
    const KEY_ACCESS_TOKEN_PROVIDER_MERCHANT = 'it:opendel:cdt:{provider}:{merchantId}:at';
    const KEY_EVENTS_ACKNOWLEDGMENT = 'it:opendel:ord:{provider}:ack';
    const KEY_EVENTS_GET_DETAILS_ORDER = 'it:opendel:ord:gd';
    const KEY_ORDER_DETAILS = 'it:opendel:ord:{provider}:dl:{order_id}';

    // Monitores
    const KEY_POLLING_EVENTS_MONITOR = 'it:opendel:mt:poll';
    const KEY_ACKNOWLEDGMENT_EVENTS_MONITOR = 'it:opendel:mt:ack';
    const KEY_GET_DETAILS_ORDER_MONITOR = 'it:opendel:mt:gd';

    // TTL
    const TTL_AUTHENTICATE_ORDER_SERVICE = 21600; // 6 horas
    const TTL_ORDER_DETAILS = 43200; // 12 horas
}
