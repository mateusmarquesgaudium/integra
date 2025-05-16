<?php

namespace src\Fcm\Http;

use src\geral\Custom;

class SendSynchronousEvents extends EventsManager
{

    public function __construct(Custom $custom)
    {
        parent::__construct($custom);
    }

    public function send(string $path, string $accessToken, array $requests): bool|string
    {
        $this->buildRequests($path, $accessToken, $requests);

        if (!$this->requestMulti->haveRequests()) {
            return false;
        }

        $responses = $this->execute();
        return $this->buildResponseRaw($responses);
    }
}