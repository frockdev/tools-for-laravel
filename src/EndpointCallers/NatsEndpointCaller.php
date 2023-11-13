<?php

namespace FrockDev\ToolsForLaravel\EndpointCallers;

use FrockDev\ToolsForLaravel\Exceptions\NatsHandledException;
use FrockDev\ToolsForLaravel\MessageObjects\NatsMessageObject;
use FrockDev\ToolsForLaravel\NatsMessengers\GrpcNatsMessenger;
use FrockDev\ToolsForLaravel\NatsMessengers\JsonNatsMessenger;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use OpenTracing\Tracer;

class NatsEndpointCaller
{
    private Application $app;
    private Tracer $tracer;

    public function __construct(Application $app, Tracer $tracer)
    {
        $this->app = $app;
        $this->tracer = $tracer;
    }

    private function setTraceId(string $traceId) {
        Log::shareContext(array_filter([
            'trace_id' => $traceId,
        ]));
    }

    public function call(array $context, NatsMessageObject $messageObject): void
    {
        $scope = $this->tracer->startActiveSpan('natsBeforeEndpointCall', [
            'tags' => [
                'nats.subject' => $messageObject->subject,
                'traceId' => $messageObject->traceId,
                'endpoint'=> $messageObject->endpointClass,
            ],
        ]);
        try {
            $endpointClass = $this->app->make($messageObject->endpointClass);
            $endpointClass->context = $context;
            $this->setTraceId($messageObject->traceId);
            $result = $endpointClass($messageObject->body);
            if ($messageObject->replyTo) {
                /** @var GrpcNatsMessenger $messenger */
                $messenger = $this->app->make(GrpcNatsMessenger::class);
                $messenger->sendMessageToChannel($messageObject->replyTo, $result);
            }
        } catch (\Throwable $e) {
            if ($messageObject->replyTo) {
                /** @var JsonNatsMessenger $messenger */
                $messenger = $this->app->make(JsonNatsMessenger::class);
                $messenger->sendMessageToChannel($messageObject->replyTo, ['error'=>$e->getMessage()]);
            }
            Log::error($e->getMessage(), [
                'traceId'=>$messageObject->traceId,
                'exception'=>$e,
                'transport'=>'nats',
                'inputMessage'=>$messageObject->body->serializeToJsonString()]);
            throw new NatsHandledException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        } finally {
            $scope->close();
        }
    }
}

