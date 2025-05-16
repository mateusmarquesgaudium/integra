<?php

namespace src\Delivery;

use MchLog;
use MchLogLevel;
use src\Delivery\Enums\Provider;
use src\geral\Custom;
use src\geral\JWTHandler;
use src\geral\RedisService;

class ProviderManagerRequest
{
    public string $provider;
    public array $dataProvider;

    private Custom $custom;
    private array $headers;
    private JWTHandler $jwtHandler;
    private array $providersAllowedJwt;
    private bool $signatureRequiredValidate;

    public function __construct(Custom $custom)
    {
        $customDelivery = $custom->getParams('delivery');
        $this->jwtHandler = new JWTHandler($customDelivery['jwt_secret']);
        $this->providersAllowedJwt = $customDelivery['providers_allowed_jwt'];
        $this->signatureRequiredValidate = true;
        $this->custom = $custom;
        $this->headers = array_change_key_case(getallheaders());
        $this->identifyProvider();
    }

    public function createSignature(string $body): string
    {
        $secretKey = null;
        if ($this->provider === Provider::IFOOD) {
            $secretKey = $this->custom->getParams($this->provider)['webhook_client_secret'];
        } elseif (in_array($this->provider, [Provider::DELIVERY_DIRETO, Provider::MACHINE])) {
            $secretKey = $this->custom->getParams($this->provider)['client_secret'];
        }
        return hash_hmac('sha256', $body, $secretKey);
    }

    public function verifySignature(string $signature): bool
    {
        $requestSignature = $this->dataProvider['signature'] ?? null;
        if (empty($requestSignature)) {
            throw new \InvalidArgumentException('Signature in header is not found');
        }

        if (!hash_equals($requestSignature, $signature)) {
            throw new \InvalidArgumentException('Signature is not valid');
        }
        return true;
    }

    public function hasSignatureRequiredValidate(): bool
    {
        return $this->signatureRequiredValidate;
    }

    private function identifyProvider(): void
    {
        if (isset($this->headers['x-ifood-signature'])) {
            $this->provider = Provider::IFOOD;
            $this->dataProvider = [
                'signature' => $this->headers['x-ifood-signature'],
            ];
        } elseif (isset($this->headers['x-deliverydireto-signature'], $this->headers['x-deliverydireto-id'])) {
            $this->provider = Provider::DELIVERY_DIRETO;
            $this->dataProvider = [
                'signature' => $this->headers['x-deliverydireto-signature'],
                'merchant_id' => $this->headers['x-deliverydireto-id'],
            ];
        } elseif (isset($this->headers['machine-signature'])) {
            $this->provider = Provider::MACHINE;
            $this->dataProvider = [
                'signature' => $this->headers['machine-signature'],
            ];
        } elseif (
            !empty($this->headers['authorization']) && $this->jwtHandler->isValid($this->headers['authorization'])
            && !empty($this->jwtHandler->lastDecoded['provider'])
            && in_array($this->jwtHandler->lastDecoded['provider'], $this->providersAllowedJwt)
        ) {
            $this->signatureRequiredValidate = false;
            $this->provider = $this->jwtHandler->lastDecoded['provider'];
            $this->dataProvider = [
                'provider' => $this->jwtHandler->lastDecoded['provider'],
            ];

            if ($this->provider === Provider::NEEMO) {
                \MchLog::logAndInfo('log_neemo', MchLogLevel::INFO, [
                    'headers' => getallheaders(),
                    'body' => json_decode(file_get_contents('php://input'), true) ?? [],
                ]);
            }

            if ($this->provider === Provider::AIQFOME) {
                \MchLog::info('log_aiqfome', [
                    'headers' => getallheaders(),
                    'body' => json_decode(file_get_contents('php://input'), true) ?? [],
                ]);
            }
        }

        if (empty($this->provider)) {
            throw new \InvalidArgumentException('Provider is not found');
        }
    }
}