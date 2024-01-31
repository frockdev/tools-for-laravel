<?php

namespace FrockDev\ToolsForLaravel\Swow\Logging;

class LogMessage
{
    public function __construct(
        string $severity,
        string $message,
        array $context
    )
    {
        $this->severity = $severity;
        $this->message = $message;
        $this->context = $context;
    }
    public $severity;
    public $message;
    public array $context;
}
