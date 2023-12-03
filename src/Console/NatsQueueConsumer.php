<?php

namespace FrockDev\ToolsForLaravel\Console;

use FrockDev\ToolsForLaravel\Events\RequestGot;
use FrockDev\ToolsForLaravel\Events\WorkerListenStarted;
use FrockDev\ToolsForLaravel\Jobs\NatsConsumerJob;
use FrockDev\ToolsForLaravel\MessageObjects\NatsMessageObject;
use FrockDev\ToolsForLaravel\NatsMessengers\GrpcNatsMessenger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NatsQueueConsumer extends Command
{
    protected $signature = 'frock:nats-consumer {--messageLimit=1}';

    public function handle(GrpcNatsMessenger $natsMessenger) {

        $natsEndpoints = config('natsEndpoints');

        $messagesGot = 0;

        foreach ($natsEndpoints as $channelName=>$endpointInfo) {
            if (array_key_exists('stream', $endpointInfo)
                && $endpointInfo['stream']!==null
                && array_key_exists('consumerName', $endpointInfo)
                && $endpointInfo['consumerName']!==null
            ) {
                $natsMessenger->subscribeToJetStream(
                    $endpointInfo,
                    function (NatsMessageObject $message) use (&$messagesGot) {
                        $messagesGot++;
                        RequestGot::dispatch();
                        $job = new NatsConsumerJob($message);
                        dispatch($job);
                    }
                );
            } else {
                $natsMessenger->subscribeAsQueueSubscriber(
                    $endpointInfo,
                    function (NatsMessageObject $message) use (&$messagesGot) {

                        $messagesGot++;
                        RequestGot::dispatch();
                        $job = new NatsConsumerJob($message);
                        dispatch($job);
                    }
                );
            }
        }

        while(true) {
            WorkerListenStarted::dispatch();
            echo 'processing...'."\n";
            try {
                $natsMessenger->process();
            } catch (\Throwable $e) {
                Log::error($e->getMessage(), ['exception'=>$e]);
                echo 'error'."\n";
            }
            echo 'loop'."\n";
            if ($messagesGot>=$this->option('messageLimit')) {
                echo 'limit Reached '."\n";
                break;
            }
        }
    }
}
