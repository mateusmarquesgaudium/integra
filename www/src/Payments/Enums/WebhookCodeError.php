<?php

namespace src\Payments\Enums;

abstract class WebhookCodeError
{
    const CODE_SUCCESS = 0;
    const CODE_DATABASE_ERROR = 1;
    const CODE_REDIS_ERROR = 2;
    const CODE_INVALID_ARGUMENT_ERROR = 3;
    const CODE_EXCEPTION_ERROR = 4;
    const CODE_THROWABLE_ERROR = 5;

    const CODE_RETRY_10 = 50;
    const CODE_RETRY_5 = 51;
    const CODE_UNLIMITED_RETRY = 53;
}
