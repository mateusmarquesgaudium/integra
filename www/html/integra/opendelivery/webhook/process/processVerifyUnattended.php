<!-- 
    Este processo tem como responsabilidade verificar os eventos não atendidos no pedido
    e enviar para a fila de notificação dos provedores, sendo assim ele é executado a cada tempo.

    Para isso, os eventos estão em uma fila ordenada por tempo, sendo assim, é verificado
    se o evento é anterior ao tempo atual, caso seja, o evento é enviado para a fila de notificação
    com o status de reject.

    Caso ocorra algum erro, ele é registrado no log 'log_opendelivery'.
 -->

<?php

require_once __DIR__ . '/../../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../../src/autoload.php';

use src\geral\Custom;
use src\geral\RedisService;
use src\Opendelivery\Enums\RedisSchema;

$custom = new Custom;
$redisClient = new RedisService;
$redisClient->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxInstances = 1;
$redisClient->checkMonitor(RedisSchema::KEY_ORDERS_EVENTS_UNATTENDED_MONITOR, $maxInstances);
$redisClient->incrMonitor(RedisSchema::KEY_ORDERS_EVENTS_UNATTENDED_MONITOR);

try {
    $currentTime = time();
    $listKeys = $redisClient->zRangeByScore(RedisSchema::KEY_ORDERS_EVENTS_UNATTENDED, 0, $currentTime);
    if (empty($listKeys) || !is_array($listKeys)) {
        throw new UnderflowException('No orders found');
    }

    $pipeline = [];
    foreach ($listKeys as $key) {
        $orderEvent = $redisClient->get($key);
        if (!empty($orderEvent)) {
            $pipeline[] = ['rPush', RedisSchema::KEY_ORDERS_EVENTS_WEBHOOK, $orderEvent];
        }
        $pipeline[] = ['unlink', $key];
        $pipeline[] = ['zRem', RedisSchema::KEY_ORDERS_EVENTS_UNATTENDED, $key];
    }

    $redisClient->pipelineCommands($pipeline);
} catch (\UnderflowException $e) {
    echo $e->getMessage();
} catch (\Throwable $t) {
    MchLog::logAndInfo('log_opendelivery', MchLogLevel::ERROR, [
        'trace' => $t->getTraceAsString(),
        'message' => $t->getMessage(),
    ]);
}

$redisClient->decr(RedisSchema::KEY_ORDERS_EVENTS_UNATTENDED_MONITOR);
