<?php

namespace src\Cache\Entities;

use src\geral\RedisService;

class EntityManagerFactory
{
    public static function create(string $entity, RedisService $redisService): EntityManager
    {
        switch ($entity) {
            case 'Enterprise':
                return new Enterprise($redisService);
            case 'Company':
                return new Company($redisService);
            case 'EnterpriseConfigurationIntegration':
                return new EnterpriseConfigurationIntegration($redisService);
            case 'EnterpriseIntegrations':
                return new EnterpriseIntegrations($redisService);
            case 'EnterpriseIntegrationsCredential':
                return new EnterpriseIntegrationsCredential($redisService);
            default:
                throw new \Exception('Entity not found');
        }
    }
}