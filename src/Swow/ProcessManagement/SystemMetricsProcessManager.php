<?php

namespace FrockDev\ToolsForLaravel\Swow\ProcessManagement;

use FrockDev\ToolsForLaravel\Swow\Processes\ProcessesRegistry;
use FrockDev\ToolsForLaravel\Swow\Processes\SystemMetricsProcess;
use Prometheus\CollectorRegistry;

class SystemMetricsProcessManager
{
    public function registerProcesses() {
        $process = $this->createProcess();
        $process->setName('system-metrics');
        ProcessesRegistry::register($process);
    }

    private function createProcess() {
        return new SystemMetricsProcess(app()->make(CollectorRegistry::class));
    }
}
