<?php

namespace FrockDev\ToolsForLaravel\Swow\Co;

use FrockDev\ToolsForLaravel\Swow\CleanEvents\ContainerCreated;
use FrockDev\ToolsForLaravel\Swow\CleanEvents\RequestFinished;
use FrockDev\ToolsForLaravel\Swow\CleanEvents\RequestStartedHandling;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\ApplicationGateway;
use Laravel\Octane\Listeners\DisconnectFromDatabases;
use Swow\Sync\WaitGroup;

class Co
{
    private static bool $fake = false;
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

    public static function fake() {
        self::$fake = true;
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

    public static $fakeCoroutines = [];

    private function addToFakeCoroutines() {
        self::$fakeCoroutines[$this->name] = [
            'name'=>$this->name,
            'function' => $this->function,
            'args' => $this->args,
            'delaySeconds' => $this->delaySeconds,
            'needCloneDiContainer' => $this->needCloneDiContainer,
            'sync' => $this->sync
        ];
    }

    public function run() {
        if (self::$fake) {
            $this->addToFakeCoroutines();
        } else {
            $this->runCoroutine(sync: $this->sync);
        }
    }

    public function runWithClonedDiContainer() {
        if (self::$fake) {
            $this->addToFakeCoroutines();
        } else {
            $this->needCloneDiContainer = true;
            $this->runCoroutine(sync: $this->sync);
        }
    }

    private function runCoroutine(bool $sync = false) {
        if ($sync===true) {
            $waitGroup = new WaitGroup();
            $waitGroup->add();
        } else {
            $waitGroup = null;
        }
        if ($this->needCloneDiContainer) {
            $oldContainer = ContextStorage::getMainApplication();
            $newContainer = clone ($oldContainer);

            $applicationGateway = new ApplicationGateway($oldContainer, $newContainer);
            $newContainer->instance(ApplicationGateway::class, $applicationGateway);
        } else {
            $newContainer = ContextStorage::getApplication();
        }
        $currentTraceId = ContextStorage::get('x-trace-id');
        $coroutine = new \Swow\Coroutine(function ($callable, Application $newContainer, $processName, $traceId, $delay, $oldContainer, ...$args) use ($waitGroup) {
            ContextStorage::setCurrentRoutineName($processName);
            ContextStorage::setApplication($newContainer);
            if ($traceId) {
                ContextStorage::set('x-trace-id', $traceId);
            }
            if ($this->needCloneDiContainer && $oldContainer) {
                $newContainer->instance('app', $newContainer);
                $newContainer->instance(\Illuminate\Foundation\Application::class, $newContainer);
                $newContainer->instance(Container::class, $newContainer);
                Container::setInstance($newContainer);

                Facade::clearResolvedInstances();
                Facade::setFacadeApplication($newContainer);

            }

            if ($delay > 0) {
                sleep($delay);
            }

            $callable(...$args);
            ContextStorage::clearStorage();
            $waitGroup?->done();
        });
        $coroutine->resume($this->function, $newContainer, $this->name, $currentTraceId, $this->delaySeconds, $oldContainer??null, ...$this->args);
        if ($sync===true) {
            $waitGroup->wait();
        }
    }
}
