<!--
    Este processo tem como responsabilidade realizar o envio dos merchants inválidos
    para a fila de merchants inválidos para que seja possível realizar a desativação
    da integração com o AiqFome.

    O processo é executado a cada tempo e realiza a consulta dos merchants inválidos,
    realiza o envio dos merchants inválidos para a fila de merchants inválidos e remove
    os merchants inválidos da lista de merchants inválidos.

    Caso ocorra algum erro durante o processo, o mesmo é registrado no log.
-->
<?php

use src\Aiqfome\Enums\RedisSchema;
use src\Aiqfome\Handlers\MerchantsRefreshTokenHandler;
use src\Aiqfome\Handlers\SendMerchantsForDeactivateIntegration;
use src\geral\Custom;
use src\geral\RedisService;
use src\geral\Request;

require_once __DIR__ . '/../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../src/autoload.php';

$custom = new Custom;
$redisService = new RedisService;
$redisService->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxInstances = 1;
$redisService->checkMonitor(RedisSchema::MONITOR_SEND_MERCHANTS_INVALID, $maxInstances);
$redisService->incrMonitor(RedisSchema::MONITOR_SEND_MERCHANTS_INVALID);

try {
    $merchantsRefreshTokenHandler = new MerchantsRefreshTokenHandler($redisService);
    $merchantsId = $merchantsRefreshTokenHandler->getMerchantsForDesactivateIntegration($custom->getParams('aiqfome')['maxMerchantsInvalid']);
    $merchantsRefreshTokenHandler->removeMerchantsForDesactivateIntegration(count($merchantsId));

    $request = new Request($custom->getParams('aiqfome')['url_send_merchants_invalid']);

    try {
        $sendMerchantsForDeactivateIntegration = new SendMerchantsForDeactivateIntegration($custom, $request, $merchantsId);
        $merchants = $sendMerchantsForDeactivateIntegration->handle();

        $pipeline = [];
        foreach ($merchantsId as $merchantId) {
            if (isset($merchants[$merchantId]) && $merchants[$merchantId] === true) {
                $pipeline[] = ['sRem', RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS, $merchantId];
                continue;
            }

            $pipeline[] = ['rPush', RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS_LIST, $merchantId];
        }
        $redisService->pipelineCommands($pipeline);
    } catch (\Throwable $th) {
        $pipeline = [];
        foreach ($merchantsId as $merchantId) {
            $pipeline[] = ['rPush', RedisSchema::KEY_AUTHENTICATE_AIQFOME_INVALID_CREDENTIALS_LIST, $merchantId];
        }

        $redisService->pipelineCommands($pipeline);
        throw $th;
    }
} catch (\UnderflowException $ue) {
    echo $ue->getMessage();
} catch (\Throwable $th) {
    MchLog::logAndInfo('log_aiqfome', MchLogLevel::ERROR, [
        'message' => $th->getMessage(),
        'trace' => $th->getTraceAsString()
    ]);
} finally {
    $redisService->decr(RedisSchema::MONITOR_SEND_MERCHANTS_INVALID);
}
