<?php

namespace FrockDev\ToolsForLaravel\Annotations;

#[\Attribute(\Attribute::TARGET_CLASS)]
class InitProcess
{
    public function __construct(
        public string $name
    ){}
}
