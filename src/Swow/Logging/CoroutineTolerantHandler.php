<?php

namespace FrockDev\ToolsForLaravel\Swow\Logging;

use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class CoroutineTolerantHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        $severity = $record->level->getName();
        $message = $record->message;
        $context = $record->context ?? [];
        $context['x-trace-id'] = ContextStorage::get('x-trace-id');
        $context['ProcessName'] = ContextStorage::getCurrentRoutineName();
        ContextStorage::getSystemChannel('log')->push(
            new LogMessage(
                $severity,
                $message,
                $context
            )
        );
    }
}
