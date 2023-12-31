<?php

namespace FrockDev\ToolsForLaravel\Exceptions;

use Throwable;

class NatsHandledException extends \Exception
{
    private string $traceId;

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null, string $traceId = '')
    {
        parent::__construct($message, $code, $previous);
        $this->traceId = $traceId;
    }
}
