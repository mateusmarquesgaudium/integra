<?php

namespace src\Aiqfome\Http;

class AuthenticateToken extends BaseAuthenticate
{
    public function setAdditionalHeaders(string $key): void
    {
        throw new \BadMethodCallException('Method not implemented');
    }

    protected function getAdditionalHeaders(): array
    {
        return [];
    }
}