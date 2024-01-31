<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Swow\Coroutine;

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
        Coroutine::run(function () {
            ContextStorage::set('processName', $this->getName());
            $this->run();
        });
    }

    abstract protected function run(): void;
}
