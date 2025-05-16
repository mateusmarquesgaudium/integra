<?php

namespace src\Opendelivery\Handlers;

use Exception;
use src\geral\Enums\RequestConstants;
use src\geral\RedisService;
use src\geral\Request;
use src\Opendelivery\Entities\EventFactory;
use src\Opendelivery\Enums\RedisSchema;
use UnderflowException;

class PollingEventsHandler
{
    private Request $request;
    private RedisService $redisService;
    private string $provider;
    private string $accessToken;

    public function __construct(RedisService $redisService, Request $request, string $provider, string $accessToken)
    {
        $this->request = $request;
        $this->redisService = $redisService;
        $this->provider = $provider;
        $this->accessToken = $accessToken;
    }

    public function execute() : void
    {
        $response = $this->request
            ->setRequestMethod('GET')
            ->setHeaders([
                'Content-Type: application/json',
                "Authorization: Bearer {$this->accessToken}",
            ])
            ->setSaveLogs(true)
            ->execute();

        $responseContent = json_decode($response->content, true);
        $httpCode = $response->http_code;
        if ($httpCode === RequestConstants::HTTP_UNAUTHORIZED){
            $key = str_replace('{provider}', $this->provider, RedisSchema::KEY_ACCESS_TOKEN_PROVIDER);
            $this->redisService->del($key);
            throw new Exception('Token inválido ou expirado');
        }

        if ($httpCode === RequestConstants::HTTP_NO_CONTENT) {
            throw new UnderflowException('Nenhum evento encontrado');
        }

        if ($httpCode !== RequestConstants::HTTP_OK) {
            throw new Exception('Erro ao obter os eventos');
        }

        $pipeline = [];
        foreach ($responseContent as $event) {
            $pipeline[] = [
                'rPush',
                str_replace('{provider}', $this->provider, RedisSchema::KEY_EVENTS_ACKNOWLEDGMENT),
                json_encode($event),
            ];

            $details = $this->redisService->get(str_replace(['{provider}', '{order_id}'], [$this->provider, $event['orderId']], RedisSchema::KEY_ORDER_DETAILS));
            $merchantId = '';
            if (!empty($details)) {
                $details = json_decode($details, true);
                $merchantId = $details['merchantId'] ?? '';
            }

            try {
                $eventFactory = new EventFactory($this->redisService, $this->provider, $merchantId);
                $eventBase = $eventFactory->create($event['eventType']);

                $event['provider'] = $this->provider;
                $eventBase->execute($event);
            } catch (\InvalidArgumentException $iae) {
                continue;
            }
        }

        $this->redisService->pipelineCommands($pipeline);
    }
}
