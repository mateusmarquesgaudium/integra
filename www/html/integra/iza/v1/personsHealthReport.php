<?php
require_once __DIR__ . '/../../../../src/autoload.php';

use src\geral\Custom;
use src\geral\ {
    RedisService,
    Util,
};
use src\iza\IzaVariables;

$custom = new Custom;

$redis = new RedisService;
$redis->connectionRedis($custom->getRedis()['hostname'], $custom->getRedis()['port']);

$resultado = [];
$querysParams = filter_input_array(INPUT_GET, FILTER_DEFAULT);
$searchBandeiraId = $querysParams['bandeira_id'] ?? null;

$keysPath = '';
if (!empty($searchBandeiraId)) {
    $keysPath = IzaVariables::KEY_EVENTS_ERROR_CREATE_PERSON . $searchBandeiraId . ':*';
} else {
    $keysPath = IzaVariables::KEY_EVENTS_ERROR_CREATE_PERSON . '*';
}

$keys = $redis->keys($keysPath);
if (!empty($keys)) {
    foreach ($keys as $key) {
        $bandeiraId = str_replace(IzaVariables::KEY_EVENTS_ERROR_CREATE_PERSON, '', $key);
        $bandeiraId = explode(':', $bandeiraId)[0] ?? null;

        if (!empty($bandeiraId)) {
            $person = $redis->get($key);
            $person = json_decode($person, true);

            if (!empty($person['taxista_id']) && (empty($searchBandeiraId) || !empty($person['erros']))) {
                $resultado[$bandeiraId][] = [
                    'taxista_id' => $person['taxista_id'],
                    'cache' => $person['erros'] ?? [],
                    'fatal_error' => $person['fatal_error'] ?? []
                ];
            }
        }
    }
} else {
    $resultado = ['message' => 'Not found problems'];
}

Util::sendJson($resultado);
