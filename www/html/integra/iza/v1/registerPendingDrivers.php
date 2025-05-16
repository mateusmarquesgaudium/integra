<?php
require_once __DIR__ . '/../../../../src/autoload.php';

use src\geral\{
    Custom,
    RedisService,
    Util,
};
use src\iza\IzaVariables;

$custom = new Custom;
$redis = new RedisService;
$redis->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$result = [];
$erros = [];
$result['success'] = false;

$postForm = filter_input_array(INPUT_POST, FILTER_DEFAULT);

if (empty($postForm)) {
    $result['erro'] = 'Falha no envio dos parametros';
    Util::sendJson($result);
}

if (empty($postForm['bandeiraId'])) {
    $erros[] = 'Bandeira id integração invalída';
}

if (!isset($postForm['condutoresPendentes'])) {
    $erros[] = 'Taxistas não enviados';
}

if (!empty($erros)) {
    $result['erros'] = $erros;
    Util::sendJson($result);
}

$bandeiraId = $postForm['bandeiraId'];
$pendingDrivers = json_decode($postForm['condutoresPendentes'], true);
$person = [];
$pipelineRegister = [];
$redis->del($redis->keys(IzaVariables::KEY_EVENTS_ERROR_CREATE_PERSON . $bandeiraId . ':*'));

foreach ($pendingDrivers as $driver) {
    $person['taxista_id'] = $driver['id'];
    $person['origem'] = IzaVariables::BD_ERRORS_ORIGIN;
    $person['erros'] = [];

    if (!empty($driver['cpf'])) {
        $person['erros'][] = IzaVariables::CPF_CODE_ERROR;
    }

    if (!empty($driver['idade'])) {
        $person['erros'][] = IzaVariables::AGE_CODE_ERROR;
    }

    $pipelineRegister[] = ['set', IzaVariables::KEY_EVENTS_ERROR_CREATE_PERSON . $bandeiraId . ':' . $person['taxista_id'], json_encode($person)];
}

$redis->pipelineCommands($pipelineRegister);
$redis->sAdd(IzaVariables::KEY_WEBHOOK_ERROR_CREATE_PERSON, $eventPerson['bandeira_id']);

$result['success'] = true;
Util::sendJson($result);
