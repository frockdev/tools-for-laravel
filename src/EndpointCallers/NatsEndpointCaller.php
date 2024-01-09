<?php

namespace FrockDev\ToolsForLaravel\EndpointCallers;

use FrockDev\ToolsForLaravel\Exceptions\NatsHandledException;
use FrockDev\ToolsForLaravel\MessageObjects\NatsMessageObject;
use FrockDev\ToolsForLaravel\NatsMessengers\GrpcNatsMessenger;
use FrockDev\ToolsForLaravel\NatsMessengers\JsonNatsMessenger;
use Google\Protobuf\Internal\Message;
use Hyperf\Coroutine\Coroutine;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use OpenTracing\Tracer;

/**
 * @deprecated
 */
class NatsEndpointCaller
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
        if (Coroutine::inCoroutine()) {

        } else {
//            Log::shareContext([
//                'trace_id' => $traceId,
//                'input'=>$message->serializeToJsonString(),
//            ]);
        }
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
            $this->addInfoToLogContext($messageObject->traceId, $messageObject->body);
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

