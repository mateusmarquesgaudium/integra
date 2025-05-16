<?php

namespace src\geral\Enums;

abstract class RequestConstants
{
    const CURLOPT_POST_JSON_ENCODE = 1;
    const CURLOPT_POST_BUILD_QUERY = 2;
    const CURLOPT_POST_NORMAL_DATA = 3;

    const HTTP_OK = 200;
    const HTTP_CREATED = 201;
    const HTTP_ACCEPTED = 202;
    const HTTP_NO_CONTENT = 204;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_UNPROCESSABLE_ENTITY = 422;
    const HTTP_INTERNAL_SERVER_ERROR = 500;
    const HTTP_SERVICE_UNAVAILABLE = 503;

    const POST = 'POST';
}
