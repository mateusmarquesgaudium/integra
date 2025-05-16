<!--
    Este processo tem como responsabilidade realizar a busca de eventos nos provedores
    e enviar para a fila de eventos, sendo assim ele é executado a cada tempo e verifica
    se há eventos na fila 'it:opendel:ord:{provider}:ack' e passa por um processo para
    obter os eventos.

    Para executar este processo é necessário informar o provedor como argumento ao executar
    o script.
-->
<?php

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

use src\Opendelivery\Handlers\GetAccessTokenHandler;
use src\Opendelivery\Handlers\PollingEventsHandler;
use src\geral\Custom;
use src\geral\RedisService;
use src\geral\Request;
use src\Opendelivery\Enums\ProvidersOpenDelivery;
use src\Opendelivery\Enums\RedisSchema;
use src\Opendelivery\Service\ProviderCredentials;

$custom = new Custom;
$redisService = new RedisService;
$redisService->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxInstances = 1;
$redisService->checkMonitor(RedisSchema::KEY_POLLING_EVENTS_MONITOR, $maxInstances);
$redisService->incrMonitor(RedisSchema::KEY_POLLING_EVENTS_MONITOR);

try {
    $provider = $argv[1];
    if (empty($provider) || !ProvidersOpenDelivery::isValidProvider($provider)) {
        throw new UnderflowException('Provider not found');
    }

    $existsEventsToAck = $redisService->exists(str_replace('{provider}', $provider, RedisSchema::KEY_EVENTS_ACKNOWLEDGMENT));
    if ($existsEventsToAck) {
        throw new UnderflowException('Exists events to acknowledge');
    }

    $providerCredentials = new ProviderCredentials($redisService, $custom, $provider);
    $getAccessTokenHandler = new GetAccessTokenHandler(
        new Request($custom->getOpendelivery()['provider'][$provider]['url'] . '/oauth/token'),
        $redisService,
        $provider,
        $providerCredentials->getClientId(''),
        $providerCredentials->getClientSecret('')
    );
    $accessToken = $getAccessTokenHandler->execute();

    $pollingEventsHandler = new PollingEventsHandler(
        $redisService,
        new Request($custom->getOpendelivery()['provider'][$provider]['url'] . '/v1/events:polling'),
        $provider,
        $accessToken
    );

    $pollingEventsHandler->execute();
} catch (\UnderflowException $ue) {
    echo $ue->getMessage() . PHP_EOL;
} catch (\Throwable $th) {
    MchLog::logAndInfo('log_opendelivery', MchLogLevel::ERROR, [
        'trace' => $th->getTraceAsString(),
        'message' => $th->getMessage(),
    ]);
}

$redisService->decr(RedisSchema::KEY_POLLING_EVENTS_MONITOR);