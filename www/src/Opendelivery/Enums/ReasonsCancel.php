<?php

namespace src\Opendelivery\Enums;

use ReflectionClass;

abstract class ReasonsCancel
{
    const CONSUMER_CANCELLATION_REQUESTED = 'CONSUMER_CANCELLATION_REQUESTED';
    const NO_SHOW = 'NO_SHOW';
    const PROBLEM_AT_MERCHANT = 'PROBLEM_AT_MERCHANT';
    const HIGH_ACCEPTANCE_TIME = 'HIGH_ACCEPTANCE_TIME';
    const INCORRECT_ORDER_OR_PRODUCT_PICKUP = 'INCORRECT_ORDER_OR_PRODUCT_PICKUP';
    const PROBLEM_RESOLUTION = 'PROBLEM_RESOLUTION';
    const DISCOMBINE_ORDER = 'DISCOMBINE_ORDER';
    const OTHER = 'OTHER';

    public static function isValidReason(string $value): bool
    {
        $reflectionClass = new ReflectionClass(__CLASS__);
        return in_array($value, $reflectionClass->getConstants());
    }

    public static function getAllReasons(): array
    {
        $reflectionClass = new ReflectionClass(__CLASS__);
        return $reflectionClass->getConstants();
    }
}