<?php

namespace src\Opendelivery\Enums;

use ReflectionClass;

abstract class ProvidersOpenDelivery
{
    const CARDAPIOWEB = 'CARDAPIOWEB';
    const ECLETICA = 'ECLETICA';
    const PROMOKIT = 'PROMOKIT';

    public static function isValidProvider(string $value): bool
    {
        $value = strtoupper($value);
        $reflectionClass = new ReflectionClass(__CLASS__);
        return in_array($value, $reflectionClass->getConstants());
    }
}
