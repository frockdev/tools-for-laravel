<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\Swow\Co\Co;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Illuminate\Support\Facades\Log;
use Swow\Sync\WaitGroup;

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

    public function runInitProcessesInCoroutine(WaitGroup $group): void
    {
        $group->add();
        Co::define($this->getName())
            ->charge(function (WaitGroup $group) {
                while (true) {
                    try {
                        $result = $this->run();
                        if ($result === false) {
                            $group->done();
                            break;
                        }
                    } catch (\Throwable $e) {
                        if (!getenv('FROCK_DEV_SERVER') === 'true') {
                            Log::critical('CRITICAL, INIT PROCESS ' . $this->getName() . ' failed with message: ' . $e->getMessage().'. Will sleep 5 sec and try to restart', ['throwable' => $e]);
                            sleep(5);
                        } else {
                            Log::info('INIT PROCESS ' . $this->getName() . ' stopped with message: ' . $e->getMessage(), ['throwable' => $e]);
                            ContextStorage::getSystemChannel('exitChannel')->push(1);
                        }
                    }
                }
            })
            ->args($group)
            ->runWithClonedDiContainer();
    }

    public function runProcessInCoroutine(): void
    {
        Co::define($this->getName())
            ->charge(function () {
                while (true) {
                    try {
                        $result = $this->run();
                        if ($result === false) {
                            return;
                        }
                    } catch (\Throwable $e) {
                        if (!getenv('FROCK_DEV_SERVER') === 'true') {
                            Log::critical('CRITICAL, Process ' . $this->getName() . ' failed with message: ' . $e->getMessage().'. Will sleep 5 sec and try to restart', ['throwable' => $e]);
                            sleep(5);
                        } else {
                            Log::info('Process ' . $this->getName() . ' stopped with message: ' . $e->getMessage(), ['throwable' => $e]);
                            ContextStorage::getSystemChannel('exitChannel')->push(1);
                        }
                    }
                }
            })
            ->runWithClonedDiContainer();
    }

    abstract protected function run(): bool;
}
