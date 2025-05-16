<?php

namespace src\Fcm\Handlers;

use src\Fcm\Entities\FcmCache;
use src\Fcm\Enums\EventWebhookType;
use src\geral\RedisService;

class WebhookHandler
{
    private RedisService $redisClient;

    public function __construct(RedisService $redisClient)
    {
        $this->redisClient = $redisClient;
    }

    public function handler(array $requestData): void
    {
        switch ($requestData['webhookType']) {
            case EventWebhookType::EVENT_FCM_BATCH:
                $fcmCache = new FcmCache($this->redisClient);
                $fcmCache->addEvents($requestData['data']);
                break;
            default:
                throw new \InvalidArgumentException('Event webhook is not found');
                break;
        }
    }
}
