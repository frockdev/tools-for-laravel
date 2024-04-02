<?php

namespace FrockDev\ToolsForLaravel\Swow\CleanEvents;

use Illuminate\Foundation\Application;

class RequestFinished
{
    public function __construct(
        public ?Application $sandbox,
    ) {
    }
}