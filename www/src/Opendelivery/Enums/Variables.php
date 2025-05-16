<?php

namespace src\Opendelivery\Enums;

abstract class Variables
{
    const TYPE_INVALID = 'INVALID';
    const GRANT_TYPE = 'client_credentials';
    const TIME_TO_EXPIRATION_UNATTENDED = 5; // Tempo em minutos

    const ORDER_TYPE_DELIVERY = 'DELIVERY';
    const ORDER_TYPE_CREATED = 'CREATED';
    const ORDER_TYPE_HIDDEN = 'HIDDEN';

    const NO_DELIVERYPERSON_AVAILABLE = 'NO_DELIVERYPERSON_AVAILABLE';
    const TIME_TO_NEXT_AVAILABLE_DELIVERY_PERSON = 5; // Tempo em minutos
}