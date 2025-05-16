<?php

namespace src\PagarMe\Handlers;

use Exception;
use RuntimeException;
use Throwable;
use UnderflowException;
use src\geral\Custom;
use src\geral\WebhookDiscord;
use src\geral\RedisService;
use src\Helpers\Webhook;
use src\PagarMe\Enums\RedisSchema;
use src\Payments\Enums\WebhookCodeError;

class PagarMeWebhook {
    private RedisService $redisClient;
    private array $custom;
    private array $customDiscord;

    public function __construct(RedisService $redisClient, Custom $custom)
    {
        $this->redisClient = $redisClient;
        $this->custom = $custom->getParams('pagarMe');
        $this->customDiscord = $custom->getParams('discord');
    }

    public function handlerWebhook(): void
    {
        if (empty($this->custom['url'])) {
            throw new UnderflowException('No URL found');
        }

        $maxOrdersAtATime = 50;
        $events = $this->redisClient->lRange(RedisSchema::LIST_PAGARME_EVENTS_WEBHOOK, 0, $maxOrdersAtATime - 1);
        if (empty($events) || !is_array($events)) {
            throw new UnderflowException('No orders found');
        }

        $this->redisClient->setNx(RedisSchema::COUNTER_RETRIES_PAGARME, 0);
        try {
            foreach ($events as $event) {
                $event = json_decode($event, true);
                if (!isset($event['url'], $event['post'], $this->custom['url'])) {
                    throw new Exception('URL ou post não encontrados');
                }
                $event['post'] = json_encode($event['post'], JSON_PRETTY_PRINT);

                $this->sendWebhook($this->custom['url'] . '/' . $event['url'], $event['post']);

                $this->redisClient->lPop(RedisSchema::LIST_PAGARME_EVENTS_WEBHOOK);
                $this->redisClient->set(RedisSchema::COUNTER_RETRIES_PAGARME, 0);
            }
        } catch (RuntimeException $re) {
            $this->redisClient->set(RedisSchema::COUNTER_RETRIES_PAGARME, 0);

            $errorCode = $re->getCode();
            if ($errorCode == WebhookCodeError::CODE_UNLIMITED_RETRY) {
                $this->redisClient->rPush(RedisSchema::LIST_ERROR_UNLIMITED_RETRY, json_encode(['error' => $re->getMessage(), 'code' => $errorCode]));
                return;
            }

            $eventError = $this->redisClient->lPop(RedisSchema::LIST_PAGARME_EVENTS_WEBHOOK);
            $eventError = json_decode($eventError, true);
            if (!isset($eventError['currentRetry'])) {
                $eventError['currentRetry'] = 1;
                $this->redisClient->lPush(RedisSchema::LIST_PAGARME_EVENTS_WEBHOOK, json_encode($eventError));
                return;
            }

            $eventError['currentRetry']++;

            $maxRetry = $errorCode == WebhookCodeError::CODE_RETRY_5 ? 5 : 10;
            if ($eventError['currentRetry'] >= $maxRetry) {
                $this->redisClient->rPush(RedisSchema::LIST_ERROR_RETRY, json_encode([
                    'error' => $re->getMessage(),
                    'code' => $errorCode,
                    'maxRetry' => $maxRetry,
                    'event' => $eventError
                ]));
                return;
            }

            $this->redisClient->lPush(RedisSchema::LIST_PAGARME_EVENTS_WEBHOOK, json_encode($eventError));
        } catch (Throwable $e) {
            $this->redisClient->set(RedisSchema::BLOCK_RETRIES_PAGARME, 1);
            $this->redisClient->expire(RedisSchema::BLOCK_RETRIES_PAGARME, 300);

            $totalIncr = $this->redisClient->incr(RedisSchema::COUNTER_RETRIES_PAGARME);
            $enableWebhookDiscord = $this->redisClient->get(RedisSchema::ENABLE_WEBHOOK_DISCORD_PAGARME);
            if ($totalIncr == 5 && !empty($enableWebhookDiscord)) {
                $this->sendDiscordWebhook();
            }

            throw new Exception('Erro ao enviar webhook da PagarMe ao monolito - ' . $e->getMessage());
        }
        return;
    }

    private function sendWebhook(string $url, string $post): void
    {
        $webhook = new Webhook($url, $post, ['Content-Type: application/json']);
        $webhook->sendMessage();
        return;
    }

    private function sendDiscordWebhook(): void
    {
        $webhookDiscord = new WebhookDiscord(
            $this->customDiscord['payments_webhook'],
            'Webhook PagarMe',
            "**ATENÇÃO**\n\n@everyone **Erro** ao enviar webhook da PagarMe ao monolito.",
            $this->custom['logo_url']
        );
        $webhookDiscord->send();
    }
}
