<?php

namespace src\Opendelivery\Entities;

use DateTime;
use DateTimeZone;
use src\Opendelivery\Enums\DeliveryStatus;

class OrderEvent
{
    private string $type;
    private DateTime $dateTime;

    public function __construct(string $event)
    {
        $this->type = $event;
        $this->dateTime = new DateTime('now', new DateTimeZone('UTC'));
    }

    public function __get($name): mixed
    {
        return $this->{$name};
    }

    public function toEventFormat(): array
    {
        return [
            'type' => $this->type,
            'dateTime' => $this->dateTime->format('c')
        ];
    }
}