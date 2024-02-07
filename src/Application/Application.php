<?php

namespace FrockDev\ToolsForLaravel\Application;

use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Swow\Coroutine;

class Application extends \Illuminate\Foundation\Application
{
    public static function getInstance()
    {
        return ContextStorage::getApplication();
    }

    /**
     * Set the shared instance of the container.
     *
     * @param  \Illuminate\Contracts\Container\Container|null  $container
     * @return \Illuminate\Contracts\Container\Container|static
     */
    public static function setInstance(ContainerContract $container = null)
    {
        ContextStorage::setApplication($container);
        return ContextStorage::getApplication();
    }
}
