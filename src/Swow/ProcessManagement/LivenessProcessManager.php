<?php

namespace FrockDev\ToolsForLaravel\Swow\ProcessManagement;

use FrockDev\ToolsForLaravel\Swow\Liveness\Storage;
use FrockDev\ToolsForLaravel\Swow\Processes\LivenessProcess;
use FrockDev\ToolsForLaravel\Swow\Processes\ProcessesRegistry;

class LivenessProcessManager
{
    public function registerProcesses() {
        $process = $this->createProcess();
        $process->setName('liveness-http');
        ProcessesRegistry::register($process);
    }

    private function createProcess() {
        return new LivenessProcess(app()->make(Storage::class));
    }
}
