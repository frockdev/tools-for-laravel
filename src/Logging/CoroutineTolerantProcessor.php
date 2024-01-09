<?php

namespace FrockDev\ToolsForLaravel\Logging;

use Co\Context;
use Hyperf\Coroutine\Coroutine;
use Hyperf\HttpServer\Router\Dispatched;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class CoroutineTolerantProcessor implements ProcessorInterface
{
    private function contextFiller(array $context): array {
        if (Coroutine::inCoroutine()) {
            $context['X-Trace-Id'] = \Hyperf\Context\Context::get('X-Trace-Id');
        }
        return $context;
    }

    public function __invoke(LogRecord $record)
    {
        $newContext = $this->contextFiller($record->context);
        $newLogRecord = new LogRecord(
            datetime: $record->datetime,
            channel: $record->channel,
            level: $record->level,
            message: $record->message,
            context: $newContext,
            extra: $record->extra,
            formatted: $record->formatted
        );
        return $newLogRecord;
    }
}
