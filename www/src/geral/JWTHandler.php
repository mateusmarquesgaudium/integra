<?php

namespace src\geral;

use Exception;

class JWTHandler
{
    private string $secretKey;
    public array $lastDecoded;

    public function __construct(string $secretKey)
    {
        $this->secretKey = $secretKey;
    }

    public function encode(array $payload): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));

        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));

        $signature = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", $this->secretKey, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";
    }

    public function decode(string $jwt): array
    {
        $segments = explode('.', $jwt);

        if (count($segments) != 3) {
            throw new Exception('Invalid token structure');
        }

        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $segments;

        $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $base64UrlHeader));
        $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $base64UrlPayload));
        $signatureProvided = base64_decode(str_replace(['-', '_'], ['+', '/'], $base64UrlSignature));

        $header = json_decode($header, true);
        if ($header['alg'] !== 'HS256') {
            throw new Exception('Unexpected algorithm');
        }

        $expectedSignature = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", $this->secretKey, true);
        if (!hash_equals($expectedSignature, $signatureProvided)) {
            throw new Exception('Invalid signature');
        }

        $payload = json_decode($payload, true);
        $this->lastDecoded = is_array($payload) ? $payload : [];
        return $this->lastDecoded;
    }

    public function isValid(string $jwt): bool
    {
        try {
            $this->decode($jwt);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}