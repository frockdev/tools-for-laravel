<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\Swow\NatsDriver;
use Illuminate\Support\Str;

class NatsQueueConsumerProcess extends AbstractProcess
{
    private object $endpoint;
    private string $subject;
    private string $queueName;
    private NatsDriver $driver;

    public function __construct(
        object $endpoint,
        string $subject,
        string $queueName,
    )
    {
        $this->endpoint = $endpoint;
        $this->subject = $subject;
        $this->queueName = $queueName;
        $this->driver = new NatsDriver($subject.'_'.Str::random()); //todo check working with singleton, but maybe change to separated connections
    }

    protected function run(): void
    {
        $this->driver->subscribeWithEndpoint(
            $this->subject,
            $this->endpoint,
            $this->queueName,
        );
    }
}
