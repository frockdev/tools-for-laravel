<?php

namespace FrockDev\ToolsForLaravel\Swow;

use FrockDev\ToolsForLaravel\Application\Application;
use Illuminate\Container\Container;

class CoroutineManager
{
    public static function runUnsafe(callable $callable, string $name, ...$args): void
    {
        \Swow\Coroutine::run($callable, ...$args);
    }

    public static function runSafe(callable $callable, string $processName, ...$args): void
    {
        $oldContainer = ContextStorage::getApplication();
        $newContainer = clone ($oldContainer);
        $innerCallable = function () use ($callable, $newContainer, $processName, $args) {
            ContextStorage::setApplication($newContainer);
            ContextStorage::set('processName', $processName);
            $newContainer->instance('app', $newContainer);
            $newContainer->instance(\Illuminate\Foundation\Application::class, $newContainer);
            $newContainer->instance(Container::class, $newContainer);

            $callable(...$args);
            ContextStorage::clearStorage();
        };
        \Swow\Coroutine::run($innerCallable, ...$args);
    }
}
