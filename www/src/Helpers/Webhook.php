<?php

namespace src\Helpers;

use RuntimeException;
use src\geral\Request;
use src\geral\Enums\RequestConstants;
use src\Payments\Enums\WebhookCodeError;

class Webhook {
    protected $url;
    protected $message;
    protected $header;

    public function __construct(string $url, string $message, array $header)
    {
        $this->url = $url;
        $this->message = $message;
        $this->header = $header;
    }

    public function sendMessage(): void
    {
        if (empty($this->url) || empty($this->message)) {
            new \Exception('URL e/ou mensagem não informados');
        }
        if (empty($this->header)) {
            $this->header = ['Content-Type: application/json'];
        }
        $request = new Request($this->url);
        $response = $request
            ->setHeaders($this->header)
            ->setRequestMethod('POST')
            ->setRequestType(RequestConstants::CURLOPT_POST_NORMAL_DATA)
            ->setPostFields($this->message)
            ->execute();

        $content = json_decode($response->content, true) ?: [];
        $this->handleResponse($response->http_code, $content);
    }

    private function handleResponse(int $httpCode, array $content): void
    {
        if ($httpCode == RequestConstants::HTTP_OK || isset($content['status']) && $content['status']) {
            return;
        }

        if (!in_array($httpCode, [RequestConstants::HTTP_BAD_REQUEST]) || empty($content) || !isset($content['code'])) {
            throw new RuntimeException('Erro ao enviar mensagem - Url: ' . $this->url . ' - Response: ' . json_encode($content), WebhookCodeError::CODE_RETRY_10);
        }

        $codeError = $content['code'];
        if ($codeError == WebhookCodeError::CODE_INVALID_ARGUMENT_ERROR) {
            return;
        }

        if (in_array($codeError, [WebhookCodeError::CODE_DATABASE_ERROR, WebhookCodeError::CODE_REDIS_ERROR])) {
            throw new RuntimeException('Erro ao enviar mensagem - Url: ' . $this->url . ' - Response: ' . json_encode($content) . ' - Error: ' . $codeError, WebhookCodeError::CODE_UNLIMITED_RETRY);
        }

        // CODE_EXCEPTION_ERROR ou CODE_THROWABLE_ERROR
        throw new RuntimeException('Erro ao enviar mensagem - Url: ' . $this->url . ' - Response: ' . json_encode($content), WebhookCodeError::CODE_RETRY_5);
    }
}