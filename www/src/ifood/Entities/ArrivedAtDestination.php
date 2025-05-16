<?php

namespace src\ifood\Entities;

use src\ifood\Enums\RedisSchema;
use src\geral\Custom;
use src\geral\RedisService;
use src\geral\Request;
use src\ifood\Http\Oauth;

class ArrivedAtDestination extends EventsIfoodManager
{
    private array $customIfood;
    private Oauth $oauth;

    public function __construct(Oauth $oauth, RedisService $redisClient)
    {
        parent::__construct($redisClient);
        $this->oauth = $oauth;
        $this->customIfood = (new Custom())->getParams('ifood');
    }

    public function send(array $event): bool
    {
        $keyEventRateLimit = str_replace('{event_type}', 'arrivedAtDestination', RedisSchema::KEY_RATE_LIMIT_ORDERS_EVENTS);
        $maxRequests = 3000;

        // Verifica se já atingiu o limite do endpoint de mudança de status
        if (!$this->checkRateLimit($keyEventRateLimit, $maxRequests)) {
            return false;
        }

        $lastEventProcessedKey = str_replace('{order_id}', $event['orderId'], RedisSchema::KEY_LAST_EVENT_PROCESSED);
        $keyEventRetry = str_replace(['{order_id}', '{event_type}'], [$event['orderId'], $event['webhookType']], RedisSchema::KEY_RETRY_ORDER_EVENTS);

        if (!$this->validateOrderEvent($event['webhookType'], $lastEventProcessedKey, $keyEventRetry)) {
            return false;
        }

        $request = new Request("{$this->customIfood['uri']}/logistics/v1.0/orders/{$event['orderId']}/arrivedAtDestination");
        $response = $request
            ->setHeaders([
                'Content-Length: 0',
                "Authorization: Bearer {$this->oauth->accessToken}"
            ])
            ->setRequestMethod('POST')
            ->setSaveLogs(true)
            ->execute();
        if (in_array($response->http_code, $this->listHttpCodeSuccessful)) {
            return true;
        }
        return false;
    }
}