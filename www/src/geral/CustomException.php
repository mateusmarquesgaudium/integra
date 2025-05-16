<?php

namespace src\geral;

class CustomException extends \Exception
{
    public string $errorMessage;
    public int $statusCode;

    public function __construct(string $message, int $statusCode = 400, int $code = 0, ?\Throwable $previous = null)
    {
        $this->errorMessage = $message;
        $this->statusCode = $statusCode;
        parent::__construct($message, $code, $previous);
    }
}