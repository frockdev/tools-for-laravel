<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\Swow\Co\Co;

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
        Co::define($this->getName())
            ->charge(function () {
                $this->run();
            })
            ->forkMain()
            ->run();
    }

    abstract protected function run(): void;
}
