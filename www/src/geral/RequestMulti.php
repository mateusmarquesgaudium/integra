<?php

namespace src\geral;

use \CurlMultiHandle;
use stdClass;

class RequestMulti {
    
    private CurlMultiHandle $curlMulti;
    private array $curlHandles;
    private array $requests;

    public function __construct(array $requests = []) {
        $this->requests = $requests;
        $this->curlHandles = [];
    }

    public function setRequests(array $requests): self {
        $this->requests = $requests;
        return $this;
    }

    public function setRequest(string $key, Request $request): self {
        $this->requests[$key] = $request;
        return $this;
    }

    public function haveRequests(): bool {
        return !empty($this->requests);
    }

    public function execute(): array {
        $this->curlMulti = curl_multi_init();

        $this->setHandles();
        
        $stillRunning = 0;
        $statusExec = -1;
        do {
            $statusExec = curl_multi_exec($this->curlMulti, $stillRunning);
        } while ($statusExec == CURLM_CALL_MULTI_PERFORM);

        while ($stillRunning && $statusExec == CURLM_OK) {
            if (curl_multi_select($this->curlMulti) != -1) {
                do {
                    $mrc = curl_multi_exec($this->curlMulti, $stillRunning);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        $curlResponses = $this->mountResult($this->curlHandles);
        curl_multi_close($this->curlMulti);
        return $curlResponses;
    }

    private function setHandles(): void {
        foreach ($this->requests as $key => $request) {
            $curlHandle = curl_init($request->getUrl());
            $this->curlHandles[$key] = $curlHandle;

            $request->setOptions();
            curl_setopt_array($curlHandle, $request->getOptions());
            curl_multi_add_handle($this->curlMulti, $curlHandle);
        }
    }

    private function mountResult(): array {
        $curlResponses = [];
        foreach ($this->curlHandles as $key => $curlHandle) {
            $curlContent = curl_multi_getcontent($curlHandle);
            $curlInfo = curl_getinfo($curlHandle);
            $curlErrno = curl_errno($curlHandle);
            $curlError = curl_error($curlHandle);

            curl_multi_remove_handle($this->curlMulti, $curlHandle);

            $response = json_decode(json_encode($curlInfo)) ?: new stdClass;
            $response->content = $curlContent;
            $response->error = $curlError;
            $response->errno = (bool) $curlErrno;

            // Latency em milisegundos
            $response->latency = round(($response?->total_time ?? 0) * 1000);

            $curlResponses[$key] = $response;
        }
        return $curlResponses;
    }
}