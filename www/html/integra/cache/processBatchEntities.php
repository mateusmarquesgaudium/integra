<?php

require_once __DIR__ . '/../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../src/autoload.php';

use src\Cache\Entities\Company;
use src\Cache\Entities\Enterprise;
use src\Cache\Entities\EnterpriseConfigurationIntegration;
use src\Cache\Entities\EnterpriseIntegrations;
use src\Cache\Entities\EnterpriseIntegrationsCredential;
use src\geral\Custom;
use src\geral\RedisService;
use src\geral\Request;

try {
    $custom = new Custom();
    $redisClient = new RedisService();
    $redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

    $scanCursor = null;
    $scanSize = 500;
    $scanPattern = 'ch:*';
    $foundKey = false;
    do {
        $keys = $redisClient->scan($scanCursor, $scanPattern, $scanSize);
        if (!empty($keys)) {
            $foundKey = true;
            break;
        }
    } while ($scanCursor != '0');

    if ($foundKey) {
        echo json_encode([
            'status' => false,
            'message' => 'Cache already filled'
        ]);
        exit;
    }

    $request = new Request($custom->getParams('cacheIntegra')['url_batch_entities']);
    $response = $request->setHeaders([
            'Content-Type: application/json',
            'authorization: ' . $custom->getParams('cacheIntegra')['token']
        ])
        ->setRequestMethod('GET')
        ->execute();
    $content = json_decode($response?->content ?? '', true);

    if (empty($content)) {
        echo json_encode([
            'status' => false,
            'message' => 'Empty response',
            'response' => $response,
        ]);
        exit;
    }

    $eventDateTime = date('Y-m-d H:i:s');
    foreach ($content['bandeiras'] as $companyId => $data) {
        $company = new Company($redisClient);
        $company->save([
            'id' => $companyId,
            'status_bandeira' => $data['status_bandeira'],
            'timestamp' => $eventDateTime
        ]);
    }

    foreach ($content['empresas'] as $enterpriseId => $data) {
        $enterprise = new Enterprise($redisClient);
        $enterprise->save([
            'id' => $enterpriseId,
            'bandeira_id' => $data['bandeira_id'],
            'status_empresa' => $data['status_empresa'],
            'timestamp' => $eventDateTime
        ]);
    }

    foreach ($content['empresasConfiguracoesIntegracoes'] as $integrationId => $data) {
        $enterpriseConfigurationIntegration = new EnterpriseConfigurationIntegration($redisClient);
        $enterpriseConfigurationIntegration->save([
            'empresa_id' => $data['empresa_id'],
            'integracao_provider_id' => $data['integracao_provider_id'],
            'integracao_habilitada' => $data['integracao_habilitada'],
        ]);
    }

    foreach ($content['empresasIntegracoes'] as $enterpriseIntegrationId => $data) {
        $enterpriseIntegrations = new EnterpriseIntegrations($redisClient);
        $enterpriseIntegrations->save([
            'id' => $enterpriseIntegrationId,
            'empresa_id' => $data['empresa_id'],
            'integracao_provider_id' => $data['integracao_provider_id'],
            'status_integracao' => $data['status_integracao'],
            'recebimento_automatico' => $data['recebimento_automatico'],
            'timestamp' => $eventDateTime
        ]);
    }

    foreach ($content['empresasIntegracoesCredenciais'] as $enterpriseIntegrationId => $data) {
        foreach ($data as $credential) {
            if (empty($credential['chave_credencial']) || empty($credential['valor_credencial'])) {
                continue;
            }

            $enterpriseIntegrationsCredentials = new EnterpriseIntegrationsCredential($redisClient);
            $enterpriseIntegrationsCredentials->save([
                'empresa_integracao_id' => $enterpriseIntegrationId,
                'chave_credencial' => $credential['chave_credencial'],
                'valor_credencial' => $credential['valor_credencial']
            ]);
        }
    }
} catch (\Throwable $t) {
    MchLog::logAndInfo('log_cache', MchLogLevel::FATAL, [
        'status' => false,
        'message' => $t->getMessage(),
        'trace' => $t->getTraceAsString()
    ]);
    exit;
}