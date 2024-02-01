<?php

namespace FrockDev\ToolsForLaravel\Swow\Logging;

use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class CoroutineTolerantLogger extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        $severity = $record->level->getName();
        $message = $record->message;
        $context = $record->context ?? [];
        $context['X-Trace-Id'] = ContextStorage::get('X-Trace-Id');
        $context['ProcessName'] = ContextStorage::get('processName');
        ContextStorage::getSystemChannel('log')->push(
            new LogMessage(
                $severity,
                $message,
                $context
            )
        );
    }
}
