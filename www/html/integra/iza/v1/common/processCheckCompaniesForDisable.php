<?php

require_once __DIR__ . '/../../../../../mchlogtoolkit/MchLog.php';
require_once __DIR__ . '/../../../../../src/autoload.php';

use src\geral\Custom;
use src\geral\RedisService;
use src\iza\IzaVariables;

$custom = new Custom;
$redis = new RedisService;
$redis->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$maxInstances = $custom->getIza()['maxInstancesCheckCompaniesForDisable'];
$redis->checkMonitor(IzaVariables::KEY_MONITOR_CHECK_COMPANIES_FOR_DISABLE, $maxInstances);
$redis->incrMonitor(IzaVariables::KEY_MONITOR_CHECK_COMPANIES_FOR_DISABLE);

$maxEventsAtATime = $custom->getIza()['maxEventsAtATimeCheckCompaniesForDisable'];

try {
    $currentTime = time();
    $listCompanies = $redis->zRangeByScore(IzaVariables::KEY_EVENTS_FORBIDDEN_REQUEST, 0, $currentTime);
    if (empty($listCompanies) || !is_array($listCompanies)) {
        throw new UnderflowException('No companies found');
    }

    $pipeline = [];
    foreach ($listCompanies as $companyId) {
        $pipeline[] = ['sAdd', IzaVariables::KEY_EVENTS_DISABLED_CENTRAL, $companyId];
        $pipeline[] = ['zRem', IzaVariables::KEY_EVENTS_FORBIDDEN_REQUEST, $companyId];
    }

    $redis->pipelineCommands($pipeline);
} catch (\UnderflowException $e) {
    echo $e->getMessage() . PHP_EOL;
} catch (\Throwable $t) {
    MchLog::logAndInfo('log_iza_process', MchLogLevel::ERROR, [
        'trace' => $t->getTraceAsString(),
        'message' => $t->getMessage(),
    ]);
}

$redis->decr(IzaVariables::KEY_MONITOR_CHECK_COMPANIES_FOR_DISABLE);