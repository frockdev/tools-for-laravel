<?php

namespace FrockDev\ToolsForLaravel\Swow\CleanEvents;

use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Illuminate\Http\Request;

class RequestStartedHandling
{
    private ?\Illuminate\Foundation\Application $app;
    private ?\Illuminate\Foundation\Application $sandbox;

    public function __construct(
        public Request $request
    ) {
        $this->app = ContextStorage::getApplication();
        $this->sandbox = ContextStorage::getApplication();
    }
}