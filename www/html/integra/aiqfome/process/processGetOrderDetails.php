<!--
    Este processo tem como responsabilidade realizar a consulta dos detalhes do pedido
    no AiqFome para que seja possível realizar as requisições de integração com o mesmo.

    O processo é executado a cada tempo e realiza a consulta dos detalhes do pedido no AiqFome
    e monta o evento do pedido para ser enviado para o monolito, para que o mesmo seja
    processado e crie o pedido no sistema.

    Caso ocorra algum erro durante o processo, o mesmo é registrado no log.
-->
<?php

use src\Aiqfome\Enums\RedisSchema;
use src\Aiqfome\Http\GetOrderDetails;
use src\geral\Custom;
use src\geral\RedisService;

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

$custom = new Custom;
$redisService = new RedisService;
$redisService->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxInstances = 1;
$redisService->checkMonitor(RedisSchema::MONITOR_GET_ORDER_DETAILS, $maxInstances);
$redisService->incrMonitor(RedisSchema::MONITOR_GET_ORDER_DETAILS);

try {
    $getOrderDetails = new GetOrderDetails($custom, $redisService);
    $getOrderDetails->execute();
} catch (UnderflowException $ue) {
    echo $ue->getMessage() . PHP_EOL;
} catch (\Throwable $th) {
    MchLog::info('log_aiqfome', [
        'trace' => $th->getTraceAsString(),
        'message' => $th->getMessage(),
    ]);
}

$redisService->decr(RedisSchema::MONITOR_GET_ORDER_DETAILS);
