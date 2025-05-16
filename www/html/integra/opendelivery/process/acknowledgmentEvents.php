<!--
    Este processo tem como responsabilidade informar ao provedor que os eventos
    foram recebidos e processados com sucesso, sendo assim ele é executado a cada
    tempo e verifica se há eventos na fila 'it:opendel:ord:{provider}:ack' e passa
    por um processo para informar ao provedor que os eventos foram processados.

    Os eventos são armazenados na fila 'it:opendel:ord:{provider}:ack' e após serem
    processados com sucesso, são removidos da fila.

    Este processo é executado para cada provedor, sendo assim, é necessário informar
    o provedor como argumento ao executar o script.
-->
<?php

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

use src\Opendelivery\Handlers\AcknowledgmentEventsHandler;
use src\Opendelivery\Handlers\GetAccessTokenHandler;
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

    $providerCredentials = new ProviderCredentials($redisService, $custom, $provider);
    $getAccessTokenHandler = new GetAccessTokenHandler(
        new Request($custom->getOpendelivery()['provider'][$provider]['url'] . '/oauth/token'),
        $redisService,
        $provider,
        $providerCredentials->getClientId(''),
        $providerCredentials->getClientSecret('')
    );
    $accessToken = $getAccessTokenHandler->execute();

    $key = str_replace('{provider}', $provider, RedisSchema::KEY_EVENTS_ACKNOWLEDGMENT);
    $events = $redisService->lRange($key, 0, $custom->getOpendelivery()['maxEvents']);

    if (empty($events)) {
        throw new UnderflowException('No events to acknowledge');
    }

    $acknowledgmentEventsHandler = new AcknowledgmentEventsHandler(
        new Request($custom->getOpendelivery()['provider'][$provider]['url'] . '/v1/events/acknowledgment'),
        $accessToken
    );

    $acknowledgmentEventsHandler->execute($events);
    $redisService->ltrim($key, count($events), -1);

} catch (\UnderflowException $ue) {
    echo $ue->getMessage() . PHP_EOL;
} catch (\Throwable $th) {
    MchLog::logAndInfo('log_opendelivery', MchLogLevel::ERROR, [
        'trace' => $th->getTraceAsString(),
        'message' => $th->getMessage(),
    ]);
}

$redisService->decr(RedisSchema::KEY_POLLING_EVENTS_MONITOR);