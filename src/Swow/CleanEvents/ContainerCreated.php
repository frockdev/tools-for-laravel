<?php

namespace FrockDev\ToolsForLaravel\Swow\CleanEvents;

use Illuminate\Foundation\Application;

class ContainerCreated
{
    public function __construct(
        public ?Application $app,
        public ?Application $sandbox,
    ) {
    }
}