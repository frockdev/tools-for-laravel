<?php

namespace FrockDev\ToolsForLaravel\HyperfProxies;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @deprecated
 */
class HyperfEventDispatcher implements EventDispatcherInterface
{

    private \Illuminate\Contracts\Events\Dispatcher $dispatcher;

    public function __construct(\Illuminate\Contracts\Events\Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function dispatch(object $event)
    {
        $this->dispatcher->dispatch($event);
    }
}
