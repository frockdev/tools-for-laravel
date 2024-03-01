<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\Swow\NatsDriver;
use Illuminate\Support\Str;

class NatsQueueConsumerProcess extends AbstractProcess
{
    private object $endpoint;
    private string $subject;
    private string $queueName;

    private bool $disableSpatieValidation = false;
    private NatsDriver $driver;

    public function __construct(
        object $endpoint,
        string $subject,
        string $queueName,
        bool $disableSpatieValidation
    )
    {
        $this->endpoint = $endpoint;
        $this->subject = $subject;
        $this->queueName = $queueName;
        $this->disableSpatieValidation = $disableSpatieValidation;
        $this->driver = new NatsDriver($subject.'_'.Str::random()); //todo check working with singleton, but maybe change to separated connections
    }

    protected function run(): bool
    {
        $this->driver->subscribeWithEndpoint(
            $this->subject,
            $this->endpoint,
            $this->queueName,
            $this->disableSpatieValidation
        );
        return false;
    }
}
