<?php

namespace src\geral;

use CurlHandle;
use MchLogLevel;
use src\geral\Enums\RequestConstants;
use stdClass;

class Request {

    private CurlHandle $curl;
    protected string $url;
    protected array $options;
    
    private int $maxRedirects;
    private int $timeout;
    private array $headers;
    private bool $followLocation;
    private string $requestMethod;
    private int $requestType;
    private array|string $postFields;
    private bool $saveLogs;

    public function __construct(string $url) {
        $this->url = rtrim($url, '/');
        $this->followLocation = false;
        $this->maxRedirects = 5;
        $this->timeout = 15;
        $this->requestType = RequestConstants::CURLOPT_POST_NORMAL_DATA;
        $this->saveLogs = false;
        $this->postFields = [];
        $this->headers = [];
    }

    public function getUrl(): string {
        return $this->url;
    }

    public function getOptions(): array {
        return $this->options;
    }

    public function setTimeout(int $timeout): self {
        $this->timeout = $timeout;
        return $this;
    }

    public function setFollowLocation(bool $followLocation): self {
        $this->followLocation = $followLocation;
        return $this;
    }

    public function setMaxRedirects(int $maxRedirects): self {
        $this->maxRedirects = $maxRedirects;
        return $this;
    }

    public function setHeaders(array $headers): self {
        $this->headers = $headers;
        return $this;
    }
    
    public function setRequestMethod(string $requestMethod): self {
        $this->requestMethod = $requestMethod;
        return $this;
    }
    
    public function setRequestType(int $requestType): self {
        $this->requestType = $requestType;
        return $this;
    }
    
    public function setPostFields(array|string $postFields): self {
        $this->postFields = $postFields;
        return $this;
    }

    public function setSaveLogs(bool $saveLogs) : self {
        $this->saveLogs = $saveLogs;
        return $this;
    }

    public function setBasicAuth(string $username, string $password): self {
        $auth = base64_encode("$username:$password");
        $this->headers[] = "Authorization: Basic $auth";

        return $this;
    }

    public function setOptions(): void {
        $this->options = [
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
        ];

        if ($this->followLocation) {
            $this->options[CURLOPT_FOLLOWLOCATION] = true;
            $this->options[CURLOPT_MAXREDIRS] = $this->maxRedirects;
        }
        if (!empty($this->headers)) {
            $this->options[CURLOPT_HTTPHEADER] = $this->headers;
        }
        if (!empty($this->requestMethod)) {
            $this->options[CURLOPT_CUSTOMREQUEST] = $this->requestMethod;
        }
        if (!empty($this->postFields)) {
            switch ($this->requestType) {
                case RequestConstants::CURLOPT_POST_JSON_ENCODE:
                    $this->options[CURLOPT_POSTFIELDS] = json_encode($this->postFields);
                    break;
                case RequestConstants::CURLOPT_POST_BUILD_QUERY:
                    $this->options[CURLOPT_POSTFIELDS] = http_build_query($this->postFields);
                    break;
                case RequestConstants::CURLOPT_POST_NORMAL_DATA:
                default:
                    $this->options[CURLOPT_POSTFIELDS] = $this->postFields;
                    break;
            }
        }
    }

    public function execute(): object {
        $this->curl = curl_init();
        $this->setOptions();
        curl_setopt_array($this->curl, $this->options);
        curl_setopt($this->curl, CURLOPT_URL, $this->url);

        $content = curl_exec($this->curl);
        $curlInfo = curl_getinfo($this->curl);
        if ($this->saveLogs) {
            require_once __DIR__ . '/../../mchlogtoolkit/MchLog.php';
            \MchLog::logAndInfo('log_request', MchLogLevel::DEBUG, [
                'content' => $content,
                'curl_info' => $curlInfo,
                'curl_data' => [
                    'followLocation' => $this->followLocation,
                    'timeout' => $this->timeout,
                    'maxRedirects' => $this->maxRedirects,
                    'requestMethod' => $this->requestMethod,
                    'requestType' => $this->requestType,
                    'headers' => $this->headers ?: [],
                    'postFields' => $this->postFields ?: [],
                ],
            ]);
        }

        $response = json_decode(json_encode($curlInfo)) ?: new stdClass;
        $response->content = $content;
        curl_close($this->curl);

        return $response;
    }
}