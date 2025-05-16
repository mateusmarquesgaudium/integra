<?php

namespace src\Fcm\Enums;

abstract class RedisSchema
{
    const KEY_LIST_EVENTS_FCM = 'fcm:events';
    const KEY_MAX_PROCESS_SEND_EVENTS = 'fcm:mx:po';
    const KEY_MONITOR_PROCESS_SEND_EVENTS = 'fcm:mt:oe';
}
