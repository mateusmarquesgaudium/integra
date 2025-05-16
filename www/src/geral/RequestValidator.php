<?php

namespace src\geral;

use src\geral\Enums\RequestConstants;

class RequestValidator
{
    private array $requiredParams;
    private string $requestMethod;
    private int $contentType;
    private string $requestRawData;
    private array $requestData;

    public function __construct(array $requiredParams, string $requestMethod, int $contentType)
    {
        $this->requiredParams = $requiredParams;
        $this->requestMethod = $requestMethod;
        $this->contentType = $contentType;
    }

    public function validateRequest(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== $this->requestMethod) {
            throw new \Exception("Invalid request method. Expected {$this->requestMethod}.");
        }

        if ($this->contentType === RequestConstants::CURLOPT_POST_JSON_ENCODE) {
            $this->requestRawData = file_get_contents('php://input');
            $this->requestData = json_decode($this->requestRawData, true) ?: [];
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid JSON data.");
            }
        } else {
            $this->requestRawData = json_encode($_REQUEST);
            $this->requestData = $_REQUEST;
        }

        $this->checkRequiredParams();
        return true;
    }

    private function checkRequiredParams(): void
    {
        $missingParams = [];

        if (empty($this->requiredParams)) {
            return;
        }

        foreach ($this->requiredParams as $param) {
            if (!$this->issetRecursive($this->requestData, $param)) {
                $missingParams[] = str_replace('.', ' ', $param);
            }
        }

        if (count($missingParams) > 0) {
            throw new \Exception("Missing required parameters: " . implode(', ', $missingParams));
        }
    }

    public function getData(): array
    {
        if (!$this->requestData) {
            throw new \Exception('Request data not set. Make sure to call validateRequest() first.');
        }
        return $this->requestData;
    }

    public function getRawData(): string
    {
        if (!$this->requestRawData) {
            throw new \Exception('Request raw data not set. Make sure to call validateRequest() first.');
        }
        return $this->requestRawData;
    }

    private function issetRecursive(array $data, string $param) : bool
    {
        if (array_key_exists($param, $data)) {
            return true;
        }

        $keys = explode('.', $param);
        $currentArray = $data;

        foreach ($keys as $key) {
            if (is_array($currentArray) && array_key_exists($key, $currentArray)) {
                $currentArray = $currentArray[$key];
            } else {
                return false;
            }
        }

        return true;
    }
}