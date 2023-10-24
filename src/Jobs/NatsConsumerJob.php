<?php

namespace FrockDev\ToolsForLaravel\Jobs;

use FrockDev\ToolsForLaravel\EndpointCallers\NatsEndpointCaller;
use FrockDev\ToolsForLaravel\MessageObjects\NatsMessageObject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NatsConsumerJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private NatsMessageObject $natsMessageObject;

    public function __construct(NatsMessageObject $natsMessageObject)
    {
        $this->natsMessageObject = $natsMessageObject;
    }

    public function handle(NatsEndpointCaller $endpointCaller)
    {
        $endpointCaller->call([], $this->natsMessageObject);
    }
}
