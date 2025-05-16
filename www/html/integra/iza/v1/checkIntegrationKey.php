<?php
require_once __DIR__ . '/../../../../src/autoload.php';

use src\geral\ {
    Util,
    Custom,
    Request
};
use src\geral\Enums\RequestConstants;

$result = [
    'success' => false,
];
$erros = [];

$postForm = filter_input_array(INPUT_POST, FILTER_DEFAULT);

if (empty($postForm)) {
    $result['erro'] = 'Falha no envio dos parametros';
    Util::sendJson($result);
}

if (empty($postForm['integrationKey'])) {
    $erros[] = 'Chave de integração invalída';
}

if (!isset($postForm['cnpj'])) {
    $erros[] = 'Cnpj não enviado';
}

if (empty($postForm['name'])) {
    $erros[] = 'Nome da central invalído';
}

if (empty($postForm['internalCodeCompany'])) {
    $erros[] = 'Código interno da central invalído';
}

if (!empty($erros)) {
    $result['erros'] = $erros;
    Util::sendJson($result);
}

$custom = new Custom;
$request = new Request($custom->getIza()['url'] . '/partner-info');
$request
    ->setHeaders($headers = [
        'Authorization: Basic ' . $postForm['integrationKey'], 
    ])
    ->setRequestMethod('GET');

$response = $request->execute();

$content = json_decode($response->content, true);
$result['content'] = json_last_error() !== JSON_ERROR_NONE ? $response->content : $content;

$result['http_code'] = $response->http_code ?? 0;

if (empty($response) || !empty($response->erros) || !in_array($response->http_code, [RequestConstants::HTTP_OK, RequestConstants::HTTP_ACCEPTED])) {
    Util::sendJson($result);
}

if (empty($content['partner_info'])) {
    Util::sendJson($result);
}

$content = $content['partner_info'];

if (!empty($content['external_id']) && $content['external_id'] == $postForm['internalCodeCompany']) {
    $result['success'] = true;
    Util::sendJson($result);
}

if (!empty($content['external_id']) && !empty($postForm['internalCodeCompanyMatriz']) && $content['external_id'] == $postForm['internalCodeCompanyMatriz']) {
    $result['success'] = true;
    Util::sendJson($result);
}

if (empty($content['doc'])) {
    Util::sendJson($result);
}

if ($content['doc'] == $postForm['cnpj']) {
    $result['success'] = true;
    Util::sendJson($result);
}

if (empty($content['name'])) {
    Util::sendJson($result);
}

$contentName = trim(strtolower($content['name']));
$postName = trim(strtolower($postForm['name']));

if ($contentName == $postName) {
    $result['success'] = true;
    Util::sendJson($result);
}

Util::sendJson($result);