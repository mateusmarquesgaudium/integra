<?php

namespace src\Cache\Enums;

abstract class Provider
{
    const IFOOD = 1;
    const DELIVERY_DIRETO = 2;
    const MENU_INTEGRADO = 3;
    const ANOTAAI = 4;
    const ALLOY_ENTREGAS = 5;
    const AIQFOME = 6;
    const JOTAJA = 7;
    const LETS_DELIVERY = 8;
    const SAIPOS = 9;
    const NEEMO = 10;
    const CARDAPIOWEB = 14;
    const DELIVERY_MUCH = 15;
    const ECLETICA = 16;
    const PROMOKIT = 18;

    public static function getProviderName(int $providerId): string
    {
        return match($providerId) {
            self::IFOOD => 'ifood',
            self::DELIVERY_DIRETO => 'delivery_direto',
            self::MENU_INTEGRADO => 'menu_integrado',
            self::ANOTAAI => 'anotaai',
            self::ALLOY_ENTREGAS => 'alloy_entregas',
            self::AIQFOME => 'aiqfome',
            self::JOTAJA => 'jotaja',
            self::LETS_DELIVERY => 'lets_delivery',
            self::SAIPOS => 'saipos',
            self::NEEMO => 'neemo',
            self::CARDAPIOWEB => 'cardapioweb',
            self::ECLETICA => 'ecletica',
            self::DELIVERY_MUCH => 'delivery_much',
            self::PROMOKIT => 'promokit',
            default => throw new \Exception('Provider not found')
        };
    }
}
