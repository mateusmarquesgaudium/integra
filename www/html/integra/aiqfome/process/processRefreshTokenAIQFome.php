<!--
    Este processo tem como responsabilidade realizar a atualização do token de autenticação
    com o AiqFome para que seja possível realizar as requisições de integração com o mesmo.

    O processo é executado a cada tempo e realiza a atualização do token de autenticação
    com o AiqFome e armazena o novo token de autenticação no Redis.

    Caso ocorra algum erro durante o processo, o mesmo é registrado no log.
-->
<?php

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

use src\Aiqfome\Enums\RedisSchema;
use src\Aiqfome\Handlers\MerchantsRefreshTokenHandler;
use src\Aiqfome\Handlers\RefreshTokenHandler;
use src\geral\Custom;
use src\geral\RedisService;

$custom = new Custom;
$redisService = new RedisService;
$redisService->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxInstances = 1;
$redisService->checkMonitor(RedisSchema::MONITOR_REFRESH_TOKEN_AIQFOME, $maxInstances);
$redisService->incrMonitor(RedisSchema::MONITOR_REFRESH_TOKEN_AIQFOME);

try {
    $merchantsRefreshTokenHandler = new MerchantsRefreshTokenHandler($redisService);
    $refreshTokenHandler = new RefreshTokenHandler($custom, $redisService, $merchantsRefreshTokenHandler);
    $refreshTokenHandler->handle();
} catch (\UnderflowException $ue) {
    echo $ue->getMessage();
} catch (\Throwable $th) {
    MchLog::logAndInfo('log_aiqfome', MchLogLevel::ERROR, [
        'trace' => $th->getTraceAsString(),
        'message' => $th->getMessage(),
    ]);
}

$redisService->decr(RedisSchema::MONITOR_REFRESH_TOKEN_AIQFOME);