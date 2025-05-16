<?php 
require_once __DIR__ . '/../../../../src/autoload.php';

use src\geral\{
    Custom,
    RedisService,
    Util,
    Request
};
use src\geral\Enums\RequestConstants;
use src\iza\IzaVariables;

$custom = new Custom;
$redis = new RedisService;
$redis->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$result = ['success' => false];
$postForm = filter_input_array(INPUT_POST, FILTER_DEFAULT);

if (empty($postForm)) {
    $result['erros'] = 'Falha no envio dos parametros';
    Util::sendJson($result);
}

if (empty($postForm['bandeiraId'])) {
    $erros[] = 'Bandeira id integração invalída';
}

if (empty($postForm['taxistaId'])) {
    $erros[] = 'Taxista não enviado';
}

if (!isset($postForm['pendencias'])) {
    $erros[] = 'Pendencias não enviadas';
}

if (!empty($erros)) {
    $result['erros'] = $erros;
    Util::sendJson($result);
}

$bandeiraId = $postForm['bandeiraId'];
$taxistaId = $postForm['taxistaId'];
$pending = json_decode($postForm['pendencias'], true);

$data = $redis->get(IzaVariables::KEY_EVENTS_ERROR_CREATE_PERSON . $bandeiraId . ':' . $taxistaId);
$driver = json_decode($data, true);

$pendingDiff = [];
foreach ($driver['erros'] as $pendingError) {
    if (empty($pending[$pendingError]['new'])) {
        $pendingDiff[] = $pendingError;
    }
}
$driver['erros'] = $pendingDiff;

$result['success'] = true;
if (!empty($driver['erros'])) {
    $redis->set(IzaVariables::KEY_EVENTS_ERROR_CREATE_PERSON . $bandeiraId . ':' . $taxistaId, json_encode($driver));
    Util::sendJson($result);
}

$redis->del(IzaVariables::KEY_EVENTS_ERROR_CREATE_PERSON . $bandeiraId . ':' . $taxistaId);

if (!empty($driver['origem'])) {
    $redis->sAdd(IzaVariables::KEY_WEBHOOK_ERROR_CREATE_PERSON, $bandeiraId);
    Util::sendJson($result);
}

$driver['doc'] = $pending[IzaVariables::CPF_CODE_ERROR]['new'] ?? $pending[IzaVariables::CPF_CODE_ERROR]['old'];
$driver['birthed_at'] = $pending[IzaVariables::AGE_CODE_ERROR]['new'] ?? $pending[IzaVariables::AGE_CODE_ERROR]['old'];
$driver['email'] = $pending[IzaVariables::EMAIL_CODE_ERROR]['new'] ?? $pending[IzaVariables::EMAIL_CODE_ERROR]['old'];
$driver['main_cell_phone'] = $pending[IzaVariables::PHONE_CODE_ERROR]['new'] ?? $pending[IzaVariables::PHONE_CODE_ERROR]['old'];

$urlIza = $custom->getIza()['url'];
$requestPerson = new Request($urlIza . '/persons');
$requestPerson
    ->setHeaders($headers = [
        'Content-Type: application/json',
        'Authorization: Basic ' . $driver['chave_integracao'],
    ])
    ->setRequestMethod('POST')
    ->setRequestType(RequestConstants::CURLOPT_POST_JSON_ENCODE)
    ->setPostFields([
        'doc' => $driver['doc'],
        'name' => $driver['name'],
        'birthed_at' => $driver['birthed_at'],
        'email' => $driver['email'],
        'main_cell_phone' => $driver['main_cell_phone'],
]);

$response = $requestPerson->execute();
$httpCode = $response?->http_code ?? null;
$content = json_decode($response?->content ?? '', true);

if (!empty($response) && !empty($content['id']) && in_array($httpCode, [RequestConstants::HTTP_OK, RequestConstants::HTTP_CREATED, RequestConstants::HTTP_ACCEPTED])) {
    $redis->sAdd(IzaVariables::KEY_WEBHOOK_ERROR_CREATE_PERSON, $bandeiraId);
    Util::sendJson($result);
}

$driver['erros'] = [];
if (!empty($content['errors'])) {
    $keyErros = array_keys($content['errors']);
    if (in_array(IzaVariables::EMAIL_CODE_ERROR, $keyErros)) {
        $driver['erros'][] = IzaVariables::EMAIL_CODE_ERROR;
    }
    if (in_array(IzaVariables::PHONE_CODE_ERROR, $keyErros)) {
        $driver['erros'][] = IzaVariables::PHONE_CODE_ERROR;
    }
    if (in_array(IzaVariables::DETAILS_CODE_ERROR, $keyErros) && $content['errors'][IzaVariables::DETAILS_CODE_ERROR] != IzaVariables::INVALID_CODE_ERROR) {
        $driver['erros'][] = $content['errors'][IzaVariables::DETAILS_CODE_ERROR];
    }
}

if (empty($eventPerson['erros'])) {
    $driver['fatal_error'] = $content;
    $redis->sAdd(IzaVariables::KEY_WEBHOOK_ERROR_CREATE_PERSON, $bandeiraId);
}

$redis->set(IzaVariables::KEY_EVENTS_ERROR_CREATE_PERSON . $bandeiraId . ':' . $taxistaId, json_encode($driver));
Util::sendJson($result);
