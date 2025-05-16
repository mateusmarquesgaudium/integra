<?php

namespace src\geral;

use DateTime;
use DateTimeZone;

class Util {

    public static function calculateTtlFromUtcDateTime(DateTime $dateTime): int
    {
        $currentTime = new DateTime('now', new DateTimeZone('UTC'));
        // Diferença em segundos
        return $currentTime->diff($dateTime)->format('%a') * 86400
               + $currentTime->diff($dateTime)->format('%h') * 3600
               + $currentTime->diff($dateTime)->format('%i') * 60
               + $currentTime->diff($dateTime)->format('%s');
    }

    public static function convertDateWithTimeZoneToDateTimeUtc(string $date): DateTime
    {
        $dateTime = new DateTime($date);
        $dateTime->setTimezone(new DateTimeZone('UTC'));
        return $dateTime;
    }

    public static function sendJson(array|string $structure, int $httpCode = 200) {
        if (!headers_sent()) {
            header('Content-type: application/json', true, $httpCode);
        }
        echo json_encode($structure);
        exit;
    }

    /**
     * Retorna o objeto do DateTime na timezone que foi informada
     *
     * @param string $timeZone Timezone que vai ser retornada a data e hora
     * @return DateTime retorna o objeto DateTime setado a timezone informada
     */
    public static function currentDateTime(string $timeZone = 'GMT'): \DateTime {
        $dateTimeZone = new \DateTimeZone($timeZone);
        return new \DateTime('now', $dateTimeZone);
    }

    /**
     * Retorna o próximo tempo para tentar reenviar o evento
     *
     * @param int $nextMultiplier Multiplicador para o tempo de retry
     * @param int $timeToRetry Tempo para tentar reenviar o evento
     * @return int Retorna o próximo tempo para tentar reenviar o evento
     */
    public static function getNextTimeToRetry(int $nextMultiplier, int $timeToRetry) : int
    {
        $currentTimestamp = time();
        $timeToRetry += random_int(0, 10); // Adiciona um valor aleatório entre 0 e 10
        return $currentTimestamp + $timeToRetry * $nextMultiplier;
    }
}