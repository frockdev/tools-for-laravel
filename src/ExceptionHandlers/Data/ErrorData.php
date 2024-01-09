<?php

namespace FrockDev\ToolsForLaravel\ExceptionHandlers\Data;

class ErrorData
{
    public int $errorCode = 500;

    public array $errorData = [
        'error'=>true
    ];
}
