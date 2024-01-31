<?php

namespace FrockDev\ToolsForLaravel\Swow\ProcessManagement;

use FrockDev\ToolsForLaravel\Swow\Processes\ProcessesRegistry;
use FrockDev\ToolsForLaravel\Swow\Processes\PrometheusHttpProcess;
use Swow\Socket;

class PrometheusHttpProcessManager
{
    public function registerProcesses() {
        $process = $this->createProcess();
        $process->setName('prometheus-http');
        ProcessesRegistry::register($process);
    }

    private function createProcess() {
        return new PrometheusHttpProcess();
    }
}
