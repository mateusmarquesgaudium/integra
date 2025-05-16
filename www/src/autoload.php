<?php

spl_autoload_register(function(string $class) {
    // Ignorar classes que não estejam dentro de src, até a gente fazer a unificação no composer
    if (strpos($class, 'src\\') === false) {
        return;
    }

    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $file = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    } else {
        throw new \Exception('Classe ' . $class . ' não encontrada.');
    }
});