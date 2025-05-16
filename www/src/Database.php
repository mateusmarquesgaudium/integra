<?php
/**
 * Arquivo responsável por armazenar todos as funções utilizadas para manipulação com bases de dados
 */

require_once __DIR__ . '/autoload.php';
use src\geral\RedisService;

 /**
 * Função responsável por criar uma conexão com o banco de dados redis e retornar essa conexão
 *
 * @param string $hostname recebe o host do redis que precisa ser conectado
 * @param int $port recebe a porta do redis que será conectado
 * @return RedisService retorna a conexão com o redis
 */
function connectionRedis($hostname, $port) {
    $redis = new RedisService;
    $redis->connectionRedis($hostname, $port);
    return $redis;
}