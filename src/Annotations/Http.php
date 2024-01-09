<?php

namespace FrockDev\ToolsForLaravel\Annotations;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Http
{
    public string $method;
    public string $path;

    public function __construct(
        string $method,
        string $path,
    )
    {
        $this->path = $path;
        $this->method = strtoupper($method);
    }
}
