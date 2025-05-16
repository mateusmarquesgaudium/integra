<?php

namespace src\Opendelivery\Entities;

abstract class EventBase
{
    abstract public function execute(array $event): void;
}
