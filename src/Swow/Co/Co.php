<?php

namespace FrockDev\ToolsForLaravel\Swow\Co;

use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
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
    private bool $needCloneDiContainer = false;
    
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
    

    public function delaySeconds(int $seconds) {
        $this->delaySeconds = $seconds;
        return $this;
    }

    public function args(...$args) {
        $this->args = $args;
        return $this;
    }

    public function sync() {
        $this->sync = true;
        return $this;
    }

    public function run() {
        $this->runCoroutine(sync: $this->sync);
    }

    public function runWithClonedDiContainer() {
        $this->needCloneDiContainer = true;
        $this->runCoroutine(sync: $this->sync);
    }

    private function runCoroutine(bool $sync = false) {
        if ($sync===true) {
            $waitGroup = new WaitGroup();
            $waitGroup->add();
        } else {
            $waitGroup = null;
        }
        if ($this->needCloneDiContainer) {
            $currentContainer = ContextStorage::getMainApplication();
            $newContainer = clone ($currentContainer);
        } else {
            $newContainer = ContextStorage::getApplication();
        }
        $currentTraceId = ContextStorage::get('x-trace-id');
        $coroutine = new \Swow\Coroutine(function ($callable, Application $newContainer, $processName, $traceId, $delay, ...$args) use ($waitGroup) {
            ContextStorage::setCurrentRoutineName($processName);
            ContextStorage::setApplication($newContainer);
            if ($traceId) {
                ContextStorage::set('x-trace-id', $traceId);
            }
            if ($this->needCloneDiContainer) {
                $newContainer->instance('app', $newContainer);
                $newContainer->instance(\Illuminate\Foundation\Application::class, $newContainer);
                $newContainer->instance(Container::class, $newContainer);
                Container::setInstance($newContainer);

                Facade::clearResolvedInstances();
                Facade::setFacadeApplication($newContainer);

                foreach (ContextStorage::getInterStreamInstances() as $key => $instance) {
                    $newContainer->instance($key, $instance);
                }
            }

            if ($delay > 0) {
                sleep($delay);
            }

            $callable(...$args);
            ContextStorage::clearStorage();
            $waitGroup?->done();
        });
        $coroutine->resume($this->function, $newContainer, $this->name, $currentTraceId, $this->delaySeconds, ...$this->args);
        if ($sync===true) {
            $waitGroup->wait();
        }
    }
}
