<?php

namespace FrockDev\ToolsForLaravel\EndpointCallers;

use FrockDev\ToolsForLaravel\Exceptions\NatsHandledException;
use FrockDev\ToolsForLaravel\MessageObjects\NatsMessageObject;
use FrockDev\ToolsForLaravel\Nats\Messengers\GrpcNatsMessenger;
use FrockDev\ToolsForLaravel\Nats\Messengers\JsonNatsMessenger;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;

class NatsEndpointCaller
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    private function setTraceId(array $ctx) {
        $header = config('frock.traceIdCtxHeader', 'X-Trace-ID');
        if (array_key_exists($header, $ctx)) {
            $traceId = $ctx[$header];
        } else {
            $traceId = Str::uuid()->toString();
        }
        Log::shareContext(array_filter([
            'trace_id' => $traceId,
        ]));
    }

    public function call(array $context, NatsMessageObject $messageObject): void
    {
        try {
            $endpointClass = $this->app->make($messageObject->endpointClass);
            $endpointClass->context = $context;
            $this->setTraceId($context);
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
            Log::error($e->getMessage(), ['traceableString'=>$messageObject->traceId, 'exception'=>$e, 'transport'=>'nats', 'inputMessage'=>$messageObject->body->serializeToJsonString()]);
            throw new NatsHandledException();
        }
    }
}

