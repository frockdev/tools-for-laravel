<?php

namespace FrockDev\ToolsForLaravel\Console;

use FrockDev\ToolsForLaravel\Events\RequestGot;
use FrockDev\ToolsForLaravel\Jobs\NatsConsumerJob;
use FrockDev\ToolsForLaravel\MessageObjects\NatsMessageObject;
use FrockDev\ToolsForLaravel\Nats\Message;
use FrockDev\ToolsForLaravel\Nats\Messengers\GrpcNatsMessenger;
use Illuminate\Console\Command;
use OpenTracing\Tracer;

class NatsQueueConsumer extends Command
{
    protected $signature = 'frock:nats-consumer {--messageLimit=1}';

    public function handle(GrpcNatsMessenger $natsMessenger) {

        $natsEndpoints = config('natsEndpoints');

        foreach ($natsEndpoints as $channelName=>$endpointInfo) {
            $natsMessenger->subscribeAsAQueueWorker(
                $channelName,
                function (Message $message) {
                    RequestGot::dispatch();
                    $endpointInfo = config('natsEndpoints.'.$message->getSubject());
                    $natsMessageObject = new NatsMessageObject(
                        $message->getSubject(),
                        $message->getReplyTo(),
                        $message->getBody(),
                        $message->getSid(),
                        $endpointInfo['endpoint'],
                        $endpointInfo['inputType'],
                        $endpointInfo['outputType'],
                        //todo should be traceId when we walk through HSUB and have headers over NATS
                    );
                    $job = new NatsConsumerJob($natsMessageObject);
                    dispatch($job);
                }
            );
        }

        $natsMessenger->waitNMessages($this->option('messageLimit'));
    }
}
