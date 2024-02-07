<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\Swow\CoroutineManager;

abstract class AbstractProcess
{
    protected string $name;

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function runProcessInCoroutine(): void
    {
        CoroutineManager::runSafeFromMain(function () {
            $this->run();
        }, $this->getName());
    }

    abstract protected function run(): void;
}
