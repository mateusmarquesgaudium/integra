<?php

namespace src\Delivery\Enums;

abstract class RedisSchema
{
    const LIST_ORDERS_EVENTS_WEBHOOK = 'it:dlv:orders';

    const KEY_LIST_ORDERS_EVENTS_IN_TRANSIT = 'it:dlv:itr';
    const KEY_LIST_ORDERS_EVENTS_FINISHED = 'it:dlv:fin';

    const KEY_PROCESS_ERROR_EVENTS = 'it:dlv:err:pee';

    const KEY_MONITOR_PROCESS_WEBHOOK_EVENTS = 'it:dlv:mt:pwe';
    const KEY_MONITOR_PROCESS_IN_TRANSIT_EVENTS = 'it:dlv:mt:pite';
    const KEY_MONITOR_PROCESS_FINISHED_EVENTS = 'it:dlv:mt:pfe';
}