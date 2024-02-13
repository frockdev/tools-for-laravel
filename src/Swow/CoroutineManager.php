<?php

namespace FrockDev\ToolsForLaravel\Swow;

use Illuminate\Container\Container;

class CoroutineManager
{
    public static function runUnsafe(callable $callable, string $name, ...$args): void
    {
        \Swow\Coroutine::run($callable, ...$args);
    }

    public static function runSafe(callable $callable, string $processName, ...$args): void
    {
        $currentApp = ContextStorage::getApplication();
        $newContainer = clone ($currentApp);
        self::runSafeWithNewContainer($callable, $processName, $newContainer, ...$args);
    }

    public static function runSafeFromMain(callable $callable, string $processName, ...$args): void
    {
        $currentApp = ContextStorage::getMainApplication();
        $newContainer = clone ($currentApp);
        self::runSafeWithNewContainer($callable, $processName, $newContainer, ...$args);
    }

    private static function runSafeWithNewContainer(callable $callable, string $processName, object $container, ...$args): void
    {
        $currentTraceId = ContextStorage::get('x-trace-id');
        $coroutine = new \Swow\Coroutine(function ($callable, $newContainer, $processName, $traceId, ...$args) {
            ContextStorage::setCurrentRoutineName($processName);
            ContextStorage::setApplication($newContainer);
            if ($traceId) {
                ContextStorage::set('x-trace-id', $traceId);
            }
            $newContainer->instance('app', $newContainer);
            $newContainer->instance(\Illuminate\Foundation\Application::class, $newContainer);
            $newContainer->instance(Container::class, $newContainer);

            $callable(...$args);
            ContextStorage::clearStorage();
        });
        $coroutine->resume($callable, $container, $processName, $currentTraceId, ...$args);
    }
}
