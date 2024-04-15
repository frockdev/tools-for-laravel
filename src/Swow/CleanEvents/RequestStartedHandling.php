<?php

namespace FrockDev\ToolsForLaravel\Swow\CleanEvents;

use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Illuminate\Http\Request;

/**
 * @deprecated
 */
class RequestStartedHandling
{
    public ?\Illuminate\Foundation\Application $app;
    public ?\Illuminate\Foundation\Application $sandbox;

    public function __construct(
        public Request $request
    ) {
        $this->app = ContextStorage::getApplication();
        $this->sandbox = ContextStorage::getApplication();
    }
}