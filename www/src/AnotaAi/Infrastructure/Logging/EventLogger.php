<?php

namespace src\AnotaAi\Infrastructure\Logging;

use MchLog;
use src\geral\RedisService;
use src\AnotaAi\Enums\RedisSchema;

class EventLogger
{
    public static function logEvent(RedisService $redisClient, mixed $event): void
    {
        $enableLogs = $redisClient->get(RedisSchema::KEY_ENABLE_LOGS);
        if (!empty($enableLogs)) {
            MchLog::info('log_anotaai', $event);
        }
    }
}
