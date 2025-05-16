<?php

namespace src\ifood\Infrastructure\Logging;

use MchLog;
use src\geral\RedisService;
use src\ifood\Enums\RedisSchema;

class IfoodEventLogger
{
    public static function logEvent(RedisService $redisClient, mixed $event): void
    {
        $enableLogs = $redisClient->get(RedisSchema::KEY_ENABLE_LOGS);
        if (!empty($enableLogs)) {
            MchLog::info('log_ifood_geral', $event);
        }
    }
}
