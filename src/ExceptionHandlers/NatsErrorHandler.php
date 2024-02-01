<?php

namespace FrockDev\ToolsForLaravel\ExceptionHandlers;

//@todo should realize and connect to Nats consumers, both time with arch fixing
class NatsErrorHandler
{
    private CommonErrorHandler $commonErrorHandler;

    public function __construct(CommonErrorHandler $commonErrorHandler)
    {
        $this->commonErrorHandler = $commonErrorHandler;
    }
}
