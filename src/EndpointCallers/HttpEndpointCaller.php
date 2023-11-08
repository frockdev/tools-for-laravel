<?php

namespace FrockDev\ToolsForLaravel\EndpointCallers;

use FrockDev\ToolsForLaravel\Exceptions\NatsHandledException;
use FrockDev\ToolsForLaravel\MessageObjects\HttpMessageObject;
use Google\Protobuf\Internal\Message;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use OpenTracing\Tracer;

class HttpEndpointCaller
{
    private Application $app;
    private Tracer $tracer;

    public function __construct(Application $app, Tracer $tracer)
    {
        $this->app = $app;
        $this->tracer = $tracer;
    }

    private function shareTraceIdToLogs(string $traceId) {
        Log::shareContext(array_filter([
            'trace_id' => $traceId,
        ]));
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
            $this->shareTraceIdToLogs($messageObject->traceId);
            return $endpointClass($messageObject->body);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), [
                'traceId'=>$messageObject->traceId,
                'exception'=>$e,
                'transport'=>'http',
                'inputMessage'=>$messageObject->body->serializeToJsonString()
            ]);
            throw new NatsHandledException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        } finally {
            $span->close();
        }
    }
}
