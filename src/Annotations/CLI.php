<?php

namespace FrockDev\ToolsForLaravel\Annotations;

#[\Attribute(\Attribute::TARGET_METHOD)]
class CLI
{
    public string $commandName;
    public function __construct(string $commandName)
    {
        $this->commandName = $commandName;
    }

}
