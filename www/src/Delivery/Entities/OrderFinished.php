<?php

namespace src\Delivery\Entities;

abstract class OrderFinished
{
    abstract public function send(array &$event, array &$pipeline): bool;
}
