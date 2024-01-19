<?php

namespace FrockDev\ToolsForLaravel\Annotations;

#[\Attribute(\Attribute::TARGET_CLASS)]
class EndpointFeatureFlag
{
    public function __construct(
        public string $name,
        public string $default = 'true',
    ) {
    }
}
