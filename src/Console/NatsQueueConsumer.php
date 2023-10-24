<?php

namespace FrockDev\ToolsForLaravel\Console;

use FrockDev\ToolsForLaravel\Jobs\NatsConsumerJob;
use FrockDev\ToolsForLaravel\MessageObjects\NatsMessageObject;
use FrockDev\ToolsForLaravel\Nats\Message;
use FrockDev\ToolsForLaravel\Nats\Messengers\GrpcNatsMessenger;
use Illuminate\Bus\Dispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NatsQueueConsumer extends Command
{
    protected $signature = 'frock:nats-consumer {--messageLimit=1}';

    public function handle(GrpcNatsMessenger $natsMessenger) {

        $natsEndpoints = config('natsEndpoints');

        foreach ($natsEndpoints as $channelName=>$endpointInfo) {
            $natsMessenger->subscribeAsAQueueWorker(
                $channelName,
                function (Message $message) {
//                    Log::debug('We got the message from channel: '.$message->getSubject()."\n".print_r($message, true));
                    $endpointInfo = config('natsEndpoints.'.$message->getSubject());
                    $natsMessageObject = new NatsMessageObject(
                        $message->getSubject(),
                        $message->getReplyTo(),
                        $message->getBody(),
                        $message->getSid(),
                        $endpointInfo['endpoint'],
                        $endpointInfo['inputType'],
                        $endpointInfo['outputType']
                    );
                    $job = new NatsConsumerJob($natsMessageObject);
                    dispatch($job);
                }
            );
        }

        $natsMessenger->waitNMessages($this->option('messageLimit'));
    }
}
