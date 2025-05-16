<?php

namespace src\Aiqfome\Http;

class RefreshToken extends BaseAuthenticate
{
    private string $refreshToken;

    public function setAdditionalHeaders(string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
    }

    protected function getAdditionalHeaders(): array
    {
        return [
            'RefreshToken: ' . $this->refreshToken,
        ];
    }
}
