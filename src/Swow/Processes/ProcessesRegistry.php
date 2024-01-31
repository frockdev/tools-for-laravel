<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

class ProcessesRegistry
{
    static private array $processes = [];

    static public function register(AbstractProcess $process): void
    {
        self::$processes[$process->getName()] = $process;
    }

    static public function runRegisteredProcesses(): void
    {
        /** @var AbstractProcess $process */
        foreach (self::$processes as $process) {
            $process->runProcessInCoroutine();
        }
    }
}
