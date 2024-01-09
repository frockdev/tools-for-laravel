<?php

namespace FrockDev\ToolsForLaravel\HyperfProxies;

use Hyperf\Contract\StdoutLoggerInterface;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

class StdOutLoggerProxy implements StdoutLoggerInterface
{
    public function emergency(\Stringable|string $message, array $context = []): void
    {
        Log::emergency($message, $context);
    }

    public function alert(\Stringable|string $message, array $context = []): void
    {
        Log::alert($message, $context);
    }

    public function critical(\Stringable|string $message, array $context = []): void
    {
        Log::critical($message, $context);
    }

    public function error(\Stringable|string $message, array $context = []): void
    {
        Log::error($message, $context);
    }

    public function warning(\Stringable|string $message, array $context = []): void
    {
        Log::warning($message, $context);
    }

    public function notice(\Stringable|string $message, array $context = []): void
    {
        Log::notice($message, $context);
    }

    public function info(\Stringable|string $message, array $context = []): void
    {
        Log::info($message, $context);
    }

    public function debug(\Stringable|string $message, array $context = []): void
    {
        Log::debug($message, $context);
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        Log::log($level, $message, $context);
    }
}
