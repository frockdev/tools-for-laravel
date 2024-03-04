<?php

namespace FrockDev\ToolsForLaravel\Swow\Co;

use FrockDev\ToolsForLaravel\Application\RegularApplication;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Illuminate\Container\Container;
use Swow\Sync\WaitGroup;

class Co
{
    private string $name;
    private array $args = [];
    private int $delaySeconds = 0;
    private \Closure $function;
    /**
     * @var true
     */
    private bool $safe = true;
    /**
     * @var true
     */
    private bool $fromMain = false;
    /**
     * @var true
     */
    private bool $sync = false;

    private function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function define(string $name) {
        return new Co($name);
    }

    public function charge(\Closure $function) {
        $this->function = $function;
        return $this;
    }

    public function forkMain() {
        $this->fromMain = true;
        return $this;
    }

    public function delaySeconds(int $seconds) {
        $this->delaySeconds = $seconds;
        return $this;
    }

    public function args(...$args) {
        $this->args = $args;
        return $this;
    }

    public function unsafe() {
        $this->safe = false;
        return $this;
    }

    public function sync() {
        $this->sync = true;
        return $this;
    }

    public function run() {
        $this->runCoroutine(sync: $this->sync, fromMain: $this->fromMain);
    }

    private function runCoroutine(bool $sync = false, bool $fromMain = false) {
        if ($fromMain) {
            $currentContainer = ContextStorage::getMainApplication();
            $currentContainer = clone $currentContainer;
        } else {
            $currentContainer = ContextStorage::getApplication();
        }

        if ($this->safe) {
            $newContainer = clone ($currentContainer);
        } else {
            $newContainer = $currentContainer;
        }
        if ($sync===true) {
            $waitGroup = new WaitGroup();
            $waitGroup->add();
        } else {
            $waitGroup = null;
        }
        $currentTraceId = ContextStorage::get('x-trace-id');
        $coroutine = new \Swow\Coroutine(function ($callable, RegularApplication $newContainer, $processName, $traceId, $delay, ...$args) use ($waitGroup) {
            ContextStorage::setCurrentRoutineName($processName);
            ContextStorage::setApplication($newContainer);
            if ($traceId) {
                ContextStorage::set('x-trace-id', $traceId);
            }
            $newContainer->instance('app', $newContainer);
            $newContainer->instance(\Illuminate\Foundation\Application::class, $newContainer);
            $newContainer->instance(Container::class, $newContainer);

            $newContainer->forgetInstancesExceptThese(config('frock.preserveObjects'));

            $newContainer->flushProviders();
            $newContainer->bootstrapWith([
                    \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
                    \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
                    \Illuminate\Foundation\Bootstrap\SetRequestForConsole::class,
                    \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
                    \Illuminate\Foundation\Bootstrap\BootProviders::class,
                ]
            );

            if ($delay > 0) {
                sleep($delay);
            }

            $callable(...$args);
            ContextStorage::clearStorage();
            $waitGroup?->done();
        });
        $coroutine->resume($this->function, $newContainer, $this->name, $currentTraceId, $this->delaySeconds, ...$this->args);
    }
}
