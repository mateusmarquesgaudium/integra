<?php

namespace src\Aiqfome\Enums;

abstract class RedisSchema
{
    // Monitores
    const MONITOR_AUTHENTICATE_AIQFOME = 'it:aiq:mt:auth';
    const MONITOR_REFRESH_TOKEN_AIQFOME = 'it:aiq:mt:ref';
    const MONITOR_SEND_MERCHANTS_INVALID = 'it:aiq:mt:smi';
    const MONITOR_GET_ORDER_DETAILS = 'it:aiq:mt:god';

    // Chaves
    const KEY_AUTHENTICATE_AIQFOME = 'it:aiq:auth:{enterprise_id}';
    const KEY_AUTHENTICATE_AIQFOME_REFRESH = 'it:aiq:auth:ref';
    const KEY_AUTHENTICATE_AIQFOME_ERR_REFRESH_MERCHANT = 'it:aiq:auth:ref:{merchant_id}';
    const KEY_AUTHENTICATE_AIQFOME_ERR_COUNT_REFRESH_MERCHANT = 'it:aiq:auth:ref:err:cnt:{merchant_id}';
    const KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS = 'it:aiq:auth:err:inv:cred';
    const KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS_LIST = 'it:aiq:auth:err:inv:cred:list';
    const KEY_LIST_ORDERS_EVENTS = 'it:aiq:ord:ev';
    const KEY_LIST_ORDERS_ERR_EVENTS = 'it:aiq:ord:err';
}