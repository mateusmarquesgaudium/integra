<?php

namespace src\Aiqfome\Handlers;

use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\Request;

class SendMerchantsForDeactivateIntegration
{
    private Custom $custom;
    private Request $request;
    private array $merchants;

    public function __construct(Custom $custom, Request $request, array $merchants)
    {
        $this->custom = $custom;
        $this->request = $request;
        $this->merchants = $merchants;
    }

    public function handle() : array
    {
        $body = [
            'merchants' => $this->merchants
        ];
        $signature = hash_hmac('sha256', json_encode($body), $this->custom->getParams('aiqfome')['signature']);

        $response = $this->request
            ->setHeaders(['Content-Type: application/json', 'x-integra-signature: ' . $signature])
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields($body)
            ->execute();

        $this->checkResponse($response);
        return json_decode($response->content ?? '', true);
    }

    private function checkResponse(object $response) : void
    {
        $content = json_decode($response->content ?? '', true);
        if (empty($content)) {
            throw new \Exception('Error to send merchants invalid');
        }
    }
}