<?php

namespace src\geral;

use \Redis;

class RedisService extends Redis {

    private int $secondsForExpireMonitor = 300; // 5 minutos

    public function __construct() {
        parent::__construct();
    }

    public function connectionRedis(string $host, int $port): void {
        $this->connect($host, $port);
        if (!$this->ping()) {
            Util::sendJson([
                'success' => false,
                'message' => 'Não foi possível realizar a conexão ao banco de dados Redis.'
            ]);
        }
    }

    public function checkMonitor(string $monitor, int $maxIntances): void {
        $monitor = $this->get($monitor);
        if (intval($monitor) >= $maxIntances) {
            Util::sendJson([
                'success' => false,
                'message' => 'Já em execução.'
            ]);
        }
    }

    public function incrMonitor(string $monitor): void {
        $this->incr($monitor);
        $this->expire($monitor, $this->secondsForExpireMonitor);
    }

    public function incrMonitorWithLimit(string $monitor, int $maxInstances): bool {
        $this->multi();

        $this->incr($monitor);
        $this->get($monitor);

        $responses = $this->exec();

        $newCount = intval($responses[1]);

        if ($newCount > $maxInstances) {
            $this->decr($monitor);
            return false;
        }

        $this->expire($monitor, $this->secondsForExpireMonitor);
        return true;
    }

    public function pipelineCommands(array $commands): null|array {
        if (empty($commands)) {
            return null;
        }
        $pipeline = $this->multi(self::PIPELINE);
        foreach ($commands as $command) {
            $function = array_shift($command);
            call_user_func_array([&$pipeline, $function], $command);
        }
        $result = $pipeline->exec();
        return $result ?: null;
    }
}