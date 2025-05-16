<?php

namespace src\Opendelivery\Handlers;

use src\geral\Enums\RequestConstants;
use src\geral\Request;

class AcknowledgmentEventsHandler
{
    private Request $request;
    private string $accessToken;

    public function __construct(Request $request, string $accessToken)
    {
        $this->request = $request;
        $this->accessToken = $accessToken;
    }

    public function execute(array $events) : void
    {
        $body = [];
        foreach ($events as $event) {
            $event = json_decode($event, true);
            $body[] = [
                'id' => $event['eventId'],
                'eventType' => $event['eventType'],
                'orderId' => $event['orderId']
            ];
        }

        $response = $this->request
            ->setRequestMethod('POST')
            ->setHeaders([
                'Content-Type: application/json',
                "Authorization: Bearer {$this->accessToken}",
            ])
            ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
            ->setPostFields($body)
            ->setSaveLogs(true)
            ->execute();

        $httpCode = $response->http_code;
        if ($httpCode === RequestConstants::HTTP_UNAUTHORIZED) {
            throw new \Exception('Access token expired or invalid');
        }

        if ($httpCode !== RequestConstants::HTTP_ACCEPTED) {
            throw new \Exception('Error in acknowledgment events');
        }
    }
}
