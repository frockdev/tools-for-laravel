<?php

namespace FrockDev\ToolsForLaravel\EndpointCallers;

use FrockDev\ToolsForLaravel\Exceptions\HttpHandledException;
use FrockDev\ToolsForLaravel\MessageObjects\HttpMessageObject;
use Google\Protobuf\Internal\Message;
use Hyperf\Context\Context;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use OpenTracing\Tracer;

/**
 * @deprecated
 */
class HttpEndpointCaller
{
    private Application $app;
    private Tracer $tracer;

    public function __construct(Application $app, Tracer $tracer)
    {
        $this->app = $app;
        $this->tracer = $tracer;
    }

    private function addInfoToLogContext(string $traceId, Message $message): void
    {
        Log::shareContext([
            'trace_id' => $traceId,
            'input'=>$message->serializeToJsonString(),
        ]);
    }

    public function call(array $context, HttpMessageObject $messageObject): Message
    {
        $span = $this->tracer->startActiveSpan('beforeEndpointCalled', [
            'tags' => [
                'endpoint' => $messageObject->endpointClass,
                'traceId' => $messageObject->traceId,
            ],
        ]);
        try {
            $endpointClass = $this->app->make($messageObject->endpointClass);
            $endpointClass->context = $context;
            $this->addInfoToLogContext($messageObject->traceId, $messageObject->body);
            return $endpointClass($messageObject->body);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), [
                'traceId'=>$messageObject->traceId,
                'exception'=>$e,
                'transport'=>'http',
                'inputMessage'=>$messageObject->body->serializeToJsonString()
            ]);
            throw new HttpHandledException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        } finally {
            $span->close();
        }
    }
}
