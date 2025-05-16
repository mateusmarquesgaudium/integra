<?php

namespace src\Cache\Entities;

abstract class EntityManager
{
    abstract public function save(array $data): void;
    abstract public function update(array $data): void;
    abstract public function delete(array $data): void;

    abstract protected function getKey(): string;
    abstract protected function checkRulesForUpdate(): bool;
    abstract protected function checkLastEventDateTime(): bool;
    abstract protected function setData(array $data): void;
}