<?php

namespace FrockDev\ToolsForLaravel\Swow\Liveness;

class DataObject
{
    public function __construct(
        public string $componentState,
        public string $componentMessage,
    ){}
}
