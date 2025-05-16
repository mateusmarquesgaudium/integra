<?php

namespace src\Opendelivery\Handlers;

use src\Cache\Enums\CacheKeys;
use src\geral\Custom;
use src\geral\CustomException;
use src\geral\RedisService;

class ValidateWebhookHandler
{
    private RedisService $redisService;
    private Custom $custom;

    public function __construct(RedisService $redisService, Custom $custom)
    {
        $this->redisService = $redisService;
        $this->custom = $custom;
    }

    public function execute(array $headers, string $body): array
    {
        $headers = array_change_key_case($headers, CASE_UPPER);
        $xAppId = $headers['X-APP-ID'] ?? null;
        if (empty($xAppId)) {
            throw new CustomException(json_encode([
                'title' => 'X-App-Id is required',
                'detail' => 'The X-App-Id header is required to validate the request'
            ]), 400);
        }

        $provider = $this->custom->getOpendelivery()['credentials'][$xAppId] ?? null;
        if (empty($provider)) {
            throw new CustomException(json_encode([
                'title' => 'Invalid X-App-Id',
                'detail' => 'The X-App-Id header is invalid'
            ]), 403);
        }

        $xAppMerchantId = $headers['X-APP-MERCHANTID'] ?? null;
        if (empty($xAppMerchantId)) {
            throw new CustomException(json_encode([
                'title' => 'X-App-MerchantId is required',
                'detail' => 'The X-App-MerchantId header is required to validate the request'
            ]), 400);
        }

        $key = str_replace(['{provider}', '{merchantId}'], [$provider, $xAppMerchantId], CacheKeys::PROVIDER_MERCHANTS_KEY);
        $enterpriseIntegrationId = $this->redisService->hGet($key, 'enterpriseIntegrationId');
        if (empty($enterpriseIntegrationId)) {
            throw new CustomException(json_encode([
                'title' => 'Invalid X-App-MerchantId',
                'detail' => 'The X-App-MerchantId header is invalid'
            ]), 403);
        }

        $signature = $headers['X-APP-SIGNATURE'] ?? null;
        if (empty($signature)) {
            throw new CustomException(json_encode([
                'title' => 'X-App-Signature is required',
                'detail' => 'The X-App-Signature header is required to validate the request'
            ]), 400);
        }

        $key = str_replace('{integrationId}', $enterpriseIntegrationId, CacheKeys::ENTERPRISE_INTEGRATION_KEY);
        $clientSecret = $this->redisService->hGet($key, 'client_secret');
        if (empty($clientSecret)) {
            throw new \Exception('Client Id not found');
        }

        $signatureHash = hash_hmac('sha256', $body, $clientSecret);
        if ($signature !== $signatureHash) {
            throw new CustomException(json_encode([
                'title' => 'Invalid X-App-Signature',
                'detail' => 'The X-App-Signature header is invalid'
            ]), 403);
        }

        return [
            'provider' => $provider,
            'merchant' => $xAppMerchantId
        ];
    }
}
