<?php

namespace FrockDev\ToolsForLaravel\Swow\CleanEvents;

use Illuminate\Http\Request;

class RequestStartedHandling
{
    public function __construct(
        public Request $request
    ) {

    }
}