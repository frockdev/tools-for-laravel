<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

class ProcessesRegistry
{
    static private array $processes = [];
    static private array $initProcesses = [];

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

    static public function runRegisteredInitProcesses(): void
    {
        $waitGroup = new \Swow\Sync\WaitGroup();
        /** @var AbstractProcess $process */
        foreach (self::$initProcesses as $process) {
            $process->runInitProcessesInCoroutine($waitGroup);
        }
        $waitGroup->wait();
    }

    public static function registerInit(AbstractProcess $process)
    {
        self::$initProcesses[$process->getName()] = $process;
    }
}
