<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\Swow\NatsDriver;
use Illuminate\Support\Str;

class NatsJetStreamConsumerProcess extends AbstractProcess
{
    private object $endpoint;
    private string $subject;
    private string $streamName;
    private ?int $periodInMicroseconds;
    private NatsDriver $driver;
    private bool $disableSpatieValidation = false;

    public function __construct(
        object $endpoint,
        string $subject,
        string $streamName,
        ?int   $periodInMicroseconds=null,
        bool   $disableSpatieValidation = false
    )
    {
        $this->endpoint = $endpoint;
        $this->disableSpatieValidation = $disableSpatieValidation;
        $this->subject = $subject;
        $this->streamName = $streamName;
        $this->periodInMicroseconds = $periodInMicroseconds;
        $this->driver = new NatsDriver($subject.'_'.$streamName.'_'.Str::random()); //todo check working with singleton, but maybe change to separated connections
    }

    protected function run(): bool
    {
        $this->driver->subscribeToJetstreamWithEndpoint(
            subject: $this->subject,
            streamName: $this->streamName,
            endpoint: $this->endpoint,
            periodInMicroseconds: $this->periodInMicroseconds,
            disableSpatieValidation: $this->disableSpatieValidation,
        );
        return false;
    }
}
