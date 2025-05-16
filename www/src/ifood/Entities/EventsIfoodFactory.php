<?php

namespace src\ifood\Entities;

use src\geral\RedisService;
use src\ifood\Http\Oauth;

class EventsIfoodFactory
{
    public static function create(string $entity, Oauth $oauth, RedisService $redisService): EventsIfoodManager
    {
        switch ($entity) {
            case 'ASSIGN_DRIVER':
                return new AssignDriver($oauth, $redisService);
            case 'GOING_TO_ORIGIN':
                return new GoingToOrigin($oauth, $redisService);
            case 'ARRIVED_AT_ORIGIN':
                return new ArrivedAtOrigin($oauth, $redisService);
            case 'DISPATCHED':
                return new Dispatched($oauth, $redisService);
            case 'ARRIVED_AT_DESTINATION':
                return new ArrivedAtDestination($oauth, $redisService);
            default:
                throw new \Exception('Entity not found');
        }
    }
}