<?php

namespace src\Neemo\Http;

use src\geral\ {
    Custom,
    Request
};
use src\geral\Enums\RequestConstants;

class OrderList
{
    private array $customNeemo;
    private string $tokenAccount;

    public function __construct(string $tokenAccount)
    {
        $this->tokenAccount = $tokenAccount;
        $this->customNeemo = (new Custom())->getParams('neemo');
    }

    public function checkAccessOrderList(): bool
    {
        $request = new Request("{$this->customNeemo['urlBase']}/order");
        $response = $request
            ->setRequestMethod('POST')
            ->setRequestType(RequestConstants::CURLOPT_POST_NORMAL_DATA)
            ->setPostFields(['token_account' => $this->tokenAccount])
            ->setSaveLogs(true)
            ->execute();
        if (in_array($response->http_code, [RequestConstants::HTTP_OK, RequestConstants::HTTP_ACCEPTED, RequestConstants::HTTP_CREATED])) {
            return true;
        }
        return false;
    }
}