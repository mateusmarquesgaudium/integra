<?php

namespace src\Delivery\Entities;

abstract class OrderInTransit
{
    abstract public function send(array &$event): bool;
}