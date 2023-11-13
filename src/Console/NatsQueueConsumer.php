<?php

namespace FrockDev\ToolsForLaravel\Console;

use FrockDev\ToolsForLaravel\Events\RequestGot;
use FrockDev\ToolsForLaravel\Jobs\NatsConsumerJob;
use FrockDev\ToolsForLaravel\MessageObjects\NatsMessageObject;
use FrockDev\ToolsForLaravel\NatsMessengers\GrpcNatsMessenger;
use Illuminate\Console\Command;

class NatsQueueConsumer extends Command
{
    protected $signature = 'frock:nats-consumer';

    public function handle(GrpcNatsMessenger $natsMessenger) {

        $natsEndpoints = config('natsEndpoints');

        foreach ($natsEndpoints as $channelName=>$endpointInfo) {
            if (array_key_exists('stream', $endpointInfo)
                && $endpointInfo['stream']!==null
                && array_key_exists('consumerName', $endpointInfo)
                && $endpointInfo['consumerName']!==null
            ) {
                $natsMessenger->subscribeToJetStream(
                    $endpointInfo,
                    function (NatsMessageObject $message) {
                        RequestGot::dispatch();
                        $job = new NatsConsumerJob($message);
                        dispatch($job);
                    }
                );
            } else {
                $natsMessenger->subscribeAsQueueSubscriber(
                    $endpointInfo,
                    function (NatsMessageObject $message) {
                        RequestGot::dispatch();
                        $job = new NatsConsumerJob($message);
                        dispatch($job);
                    }
                );
            }
        }
        $natsMessenger->process();
    }
}
