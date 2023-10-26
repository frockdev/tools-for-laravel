<?php

namespace FrockDev\ToolsForLaravel\Annotations;
#[\Attribute(\Attribute::TARGET_CLASS)]
class ShouldBeTested
{
    public array $methods = [];

    /**
     * @param array|string[] $methods
     */
    public function __construct(array $methods)
    {
        $this->methods = $methods;
    }
}
