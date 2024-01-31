<?php

namespace FrockDev\ToolsForLaravel\Swow\Logging;

use Monolog\Level;
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
                new CoroutineTolerantLogger($config['level'] ?? Level::Debug),
            ]
        );
        $logger->useLoggingLoopDetection(false);
        return $logger;
    }
}
