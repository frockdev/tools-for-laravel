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

                $this->addEventsToNewContainer($newContainer);
                $this->registerOnRequestEventHandlers($newContainer);
                $this->fireContainerClonedEvent($oldContainer, $newContainer);

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
        $coroutine->resume($this->function, $newContainer, $this->name, $currentTraceId, $this->delaySeconds, $oldContainer??null, ...$this->args);
        if ($sync===true) {
            $waitGroup->wait();
        }
    }

    private function addEventsToNewContainer(Application $newContainer)
    {
        /** @var Dispatcher $dispatcher */
        $dispatcher = $newContainer->make(\Illuminate\Contracts\Events\Dispatcher::class);

        $events = [
//            \Laravel\Octane\Listeners\CreateConfigurationSandbox::class,
            \Laravel\Octane\Listeners\GiveNewApplicationInstanceToAuthorizationGate::class,
            \Laravel\Octane\Listeners\GiveNewApplicationInstanceToBroadcastManager::class,
            \Laravel\Octane\Listeners\GiveNewApplicationInstanceToDatabaseManager::class,
            \Laravel\Octane\Listeners\GiveNewApplicationInstanceToDatabaseSessionHandler::class,
            \Laravel\Octane\Listeners\GiveNewApplicationInstanceToFilesystemManager::class,
            \Laravel\Octane\Listeners\GiveNewApplicationInstanceToHttpKernel::class,
            \Laravel\Octane\Listeners\GiveNewApplicationInstanceToMailManager::class,
            \Laravel\Octane\Listeners\GiveNewApplicationInstanceToNotificationChannelManager::class,
            \Laravel\Octane\Listeners\GiveNewApplicationInstanceToPipelineHub::class,
            \Laravel\Octane\Listeners\GiveNewApplicationInstanceToCacheManager::class,
            \Laravel\Octane\Listeners\GiveNewApplicationInstanceToSessionManager::class,
            \Laravel\Octane\Listeners\GiveNewApplicationInstanceToQueueManager::class,
            \Laravel\Octane\Listeners\GiveNewApplicationInstanceToRouter::class,
            \Laravel\Octane\Listeners\GiveNewApplicationInstanceToValidationFactory::class,
            \Laravel\Octane\Listeners\GiveNewApplicationInstanceToViewFactory::class,
            \Laravel\Octane\Listeners\FlushDatabaseRecordModificationState::class,
            \Laravel\Octane\Listeners\FlushDatabaseQueryLog::class,
            \Laravel\Octane\Listeners\RefreshQueryDurationHandling::class,
            \Laravel\Octane\Listeners\FlushLogContext::class,
            \Laravel\Octane\Listeners\FlushArrayCache::class,
            \Laravel\Octane\Listeners\FlushMonologState::class,
            \Laravel\Octane\Listeners\FlushStrCache::class,
            \Laravel\Octane\Listeners\FlushTranslatorCache::class,

            // First-Party Packages...
            \Laravel\Octane\Listeners\PrepareInertiaForNextOperation::class,
            \Laravel\Octane\Listeners\PrepareLivewireForNextOperation::class,
            \Laravel\Octane\Listeners\PrepareScoutForNextOperation::class,
            \Laravel\Octane\Listeners\PrepareSocialiteForNextOperation::class,


            \Laravel\Octane\Listeners\FlushLocaleState::class,
            \Laravel\Octane\Listeners\FlushQueuedCookies::class,
            \Laravel\Octane\Listeners\FlushSessionState::class,
            \Laravel\Octane\Listeners\FlushAuthenticationState::class,



//            \Laravel\Octane\Listeners\EnforceRequestScheme::class,
//            \Laravel\Octane\Listeners\EnsureRequestServerPortMatchesScheme::class,
//            \Laravel\Octane\Listeners\GiveNewRequestInstanceToApplication::class,
//            \Laravel\Octane\Listeners\GiveNewRequestInstanceToPaginator::class,
        ];

        foreach ($events as $event) {
            $dispatcher->listen(ContainerCreated::class, $event);
        }


        $eventsAfterRequestFinished = [
            DisconnectFromDatabases::class
        ];

        foreach ($eventsAfterRequestFinished as $event) {
            $dispatcher->listen(RequestFinished::class, $event);
        }
    }

    private function registerOnRequestEventHandlers(Application $newApp) {
        $events = [
            \Laravel\Octane\Listeners\GiveNewRequestInstanceToApplication::class,
            \Laravel\Octane\Listeners\GiveNewRequestInstanceToPaginator::class,
        ];
        /** @var Dispatcher $dispatcher */
        $dispatcher = $newApp->make(\Illuminate\Contracts\Events\Dispatcher::class);
        foreach ($events as $event) {
            $dispatcher->listen(RequestStartedHandling::class, $event);
        }
    }

    private function fireContainerClonedEvent(Application $oldApp, Application $newApp)
    {
        /** @var Dispatcher $dispatcher */
        $dispatcher = $newApp->make(\Illuminate\Contracts\Events\Dispatcher::class);
        $dispatcher->dispatch(new ContainerCreated($oldApp, $newApp));
    }
}
