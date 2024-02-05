<?php

namespace FrockDev\ToolsForLaravel\Swow\ProcessManagement;

use FrockDev\ToolsForLaravel\Annotations\Http;
use FrockDev\ToolsForLaravel\AnnotationsCollector\Collector;
use FrockDev\ToolsForLaravel\AnnotationsObjectModels\Annotation;
use FrockDev\ToolsForLaravel\Swow\Processes\HttpProcess;
use FrockDev\ToolsForLaravel\Swow\Processes\RpcHttpProcess;
use FrockDev\ToolsForLaravel\Swow\Processes\ProcessesRegistry;

class HttpProcessManager
{
    public function registerProcesses() {
        $process = $this->createProcess();
        $process->setName('http');
        ProcessesRegistry::register($process);
    }

    private function createProcess() {
        return new HttpProcess();
    }
}
