<?php

namespace src\DeliveryMuch\Http;

use src\geral\Enums\RequestConstants;
use src\geral\Request;

class CreateIntegration
{
    private Request $request;
    private string $clientEmail;
    private string $clientPassword;
    private string $companyId;
    private string $apiKey;

    public function __construct(Request $request, string $clientEmail, string $clientPassword, string $companyId, string $apiKey)
    {
        $this->request = $request;
        $this->clientEmail = $clientEmail;
        $this->clientPassword = $clientPassword;
        $this->companyId = $companyId;
        $this->apiKey = $apiKey;
    }

    public function createIntegration(): string
    {
        $contentRequest = [
            'login' => $this->clientEmail,
            'password' => $this->clientPassword,
            'token' => $this->apiKey,
            'companyID' => $this->companyId,
        ];

        $response = $this->request
            ->setRequestMethod('POST')
            ->setHeaders(['Content-Type: application/json'])
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields($contentRequest)
            ->setSaveLogs(true)
            ->execute();

        if ($response->http_code == RequestConstants::HTTP_OK) {
            $responseData = json_decode($response->content, true);
            if (!empty($responseData) && isset($responseData[0]['_id'])) {
                return $responseData[0]['_id'];
            }
        }

        throw new \Exception('Erro ao cadastrar integração da Delivery Much.');
    }
}
