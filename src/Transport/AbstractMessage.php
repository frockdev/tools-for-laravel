<?php

namespace FrockDev\ToolsForLaravel\Transport;

use Spatie\LaravelData\Data;

abstract class AbstractMessage extends Data
{
    public array $context = [];

    public function __construct()
    {
        $this->except('context');
    }
}
