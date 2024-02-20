<?php

namespace FrockDev\ToolsForLaravel\Exceptions;

class HttpClientException extends \Exception
{

    public HttpClientExceptionData $exceptionData;

    public function __construct(\Throwable $exception, HttpClientExceptionData $exceptionData)
    {
        $message = "HTTP request to ".$exceptionData->url." failed with exception";
        $this->exceptionData = $exceptionData;
        parent::__construct($message, 424, $exception);
    }

}
