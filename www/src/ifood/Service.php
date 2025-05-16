<?php
require_once __DIR__ . '/../autoload.php';

use src\geral\Custom;

$custom = (new Custom())->getAll();

//Realiza requisição a uma URL (igual ao callUrl porém com menos parâmetros)
function callUrl2($url, $timeout_ms = null, $opcoes = []) {
    $code = null;
    $err_code = null;
    $err_message = null;
    $header = null;
    return callUrl($url, $code, $err_code, $err_message, $timeout_ms, $opcoes, $header);
}

//Realiza requisição a uma URL
function callUrl($url, &$code, &$err_code, &$err_message, $timeout_ms = null, $opcoes = [], &$header = null, &$time_response = null) {
    $code = null;
    $err_code = null;
    $err_message = null;

    if (empty($opcoes)) {
        $opcoes = [];
    }
    $opcoes['metodo'] = isset($opcoes['metodo']) ? $opcoes['metodo'] : 'GET';
    $opcoes['dados'] = isset($opcoes['dados']) ? $opcoes['dados'] : null;
    $opcoes['postfield_opt'] = isset($opcoes['postfield_opt']) ? $opcoes['postfield_opt'] : 'http_build_query';
    $opcoes['header'] = isset($opcoes['header']) ? $opcoes['header'] : null;
    $followLocation = isset($opcoes['followLocation']) ? $opcoes['followLocation'] : false;
    $log = isset($opcoes['log']) ? $opcoes['log'] : false;
    $logParametrosEnviados = isset($opcoes['logParametrosEnviados']) ? $opcoes['logParametrosEnviados'] : true;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $opcoes['metodo']);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (!empty($timeout_ms)) {
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeout_ms);
    }
    if (!empty($opcoes['dados'])) {
        if ($opcoes['postfield_opt'] == 'http_build_query') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($opcoes['dados']));
        } elseif($opcoes['postfield_opt'] == 'json_encode') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opcoes['dados']));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $opcoes['dados']);
        }
    }
    if(!empty($opcoes['header'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $opcoes['header']);
    }
    if ($followLocation) {
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $maxredirect = isset($opcoes['maxredirect']) ? $opcoes['maxredirect'] : 5;
        curl_setopt($ch, CURLOPT_MAXREDIRS, $maxredirect);
    }

    if (empty($url)) {
        $err_code = -1;
        $err_message = "URL is empty";
        return false;
    } else {
        $response = curl_exec($ch);

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);
        $header = substr($response, 0, $headerSize);
        $time_response = curl_getinfo($ch,CURLINFO_TOTAL_TIME);
        $err_code = curl_errno($ch);
        $err_message = curl_error($ch);

        if ($log) {
            $d = is_null($opcoes['dados']) ? null : json_encode($opcoes['dados']);
            $d = $logParametrosEnviados ? $d : null;

            $params = [
                ':servico' => $url,
                ':parametros_enviados' => $d,
                ':resposta_recebida' => $body,
                ':status_http_resposta' => $code
            ];

            $now = agora();
            $params[':data_hora'] = $now->format("Y-m-d H:i:s");
            $params[':header'] = is_null($opcoes['header']) ? null : json_encode($opcoes['header']);
            
            MchLog::logAndInfo('log_integracao', MchLogLevel::DEBUG, $params);
        }

        if ($err_code) {
            return false;
        }

        $err_code = null;
        return json_decode($body, true);
    }
}

function agora($timeZone = 'GMT') {
    $tz_obj = new DateTimeZone($timeZone);
    return new DateTime("now", $tz_obj);
}

//Retorna um json
function sendJsonStructure($structure) {
    header('Content-type: application/json');
    echo json_encode($structure);
    exit();
}