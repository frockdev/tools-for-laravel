<?php

namespace FrockDev\ToolsForLaravel\Swow\Logging;

use Monolog\Logger;

class CustomLogger
{
    /**
     * Create a custom Monolog instance.
     */
    public function __invoke(array $config): Logger
    {
        $logger = new Logger(
            env('APP_NAME'),
            [
                new CoroutineTolerantLogger(),
            ]
        );
        $logger->useLoggingLoopDetection(false);
        return $logger;
    }
}
