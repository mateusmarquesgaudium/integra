<?php

namespace src\Fcm\Http;

require_once __DIR__ . '/../../vendor/autoload.php';

use function Amp\Parallel\Worker\enqueueCallable;
use function Amp\Promise\wait;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\Request;
use src\geral\RequestMulti;

abstract class EventsManager
{
    private int $secondsTimeout = 10;
    protected array $customFcm;
    protected RequestMulti $requestMulti;

    protected function __construct(Custom $custom)
    {
        $this->customFcm = $custom->getParams('fcm');
    }

    abstract public function send(string $path, string $accessToken, array $requests): bool|string;

    protected function buildRequests(string $path, string $accessToken, array $requests): void
    {
        $this->requestMulti = new RequestMulti();

        foreach ($requests as $index => $requestData) {
            $request = new Request($this->customFcm['baseUrl'] . $path);
            $request
                ->setHeaders([
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $accessToken,
                ])
                ->setRequestMethod('POST')
                ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
                ->setPostFields($requestData)
                ->setTimeout($this->secondsTimeout);
            $this->requestMulti->setRequest("push$index", $request);
        }
    }

    protected function buildResponseRaw(array $responses): string
    {
        $responseRaw = '';
        foreach ($responses as $index => $response) {
            if (empty($response->content)) {
                continue;
            }

            $boundaryBatch = 'batch_' . $index;

            $responseRaw .= "--$boundaryBatch\r\n";
            $responseRaw .= "Content-Type: application/http\r\n";
            $responseRaw .= "{$response->content}\r\n";
            $responseRaw .= "--$boundaryBatch--\r\n";
        }

        return $responseRaw;
    }

    protected function execute(): mixed
    {
        $promise = enqueueCallable([self::class, 'executeSubprocess'], $this->requestMulti);
        return wait($promise);
    }

    public static function executeSubprocess(RequestMulti $requestMulti): array
    {
        return $requestMulti->execute();
    }
}
