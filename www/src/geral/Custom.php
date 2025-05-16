<?php

namespace src\geral;

class Custom {
    
    private array $custom;

    public function __construct() {
        $this->custom = require __DIR__ . '/../../config/custom.php';
        return $this;
    }

    public function getAll(): array {
        return $this->custom;
    }

    public function getRedis(): array {
        return $this->custom['redis'] ?? [];
    }

    public function getIfood(): array {
        return $this->custom['params']['ifood'] ?? [];
    }

    public function getIza(): array {
        return $this->custom['params']['Iza'] ?? [];
    }

    public function getOpenDelivery(): array {
        return $this->custom['params']['openDelivery'] ?? [];
    }

    public function getDeliveryMuch(): array {
        return $this->custom['params']['delivery_much'] ?? [];
    }

    public function getParams(string $param): array {
        return $this->custom['params'][$param] ?? [];
    }

    public function getParam(string $param): string | int {
        return $this->custom['params'][$param] ?? '';
    }
}