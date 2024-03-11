<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use Basis\Nats\Consumer\AckPolicy;
use Basis\Nats\Consumer\DeliverPolicy;
use FrockDev\ToolsForLaravel\Swow\Co\Co;
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
    private string $deliverPolicy = DeliverPolicy::NEW;
    private string $ackPolicy = AckPolicy::NONE;

    public function __construct(
        object $endpoint,
        string $subject,
        string $streamName,
        ?int   $periodInMicroseconds=null,
        bool   $disableSpatieValidation = false,
        string $deliverPolicy = DeliverPolicy::NEW,
        string $ackPolicy = AckPolicy::NONE
    )
    {
        $this->endpoint = $endpoint;
        $this->disableSpatieValidation = $disableSpatieValidation;
        $this->subject = $subject;
        $this->streamName = $streamName;
        $this->periodInMicroseconds = $periodInMicroseconds;
        $this->deliverPolicy = $deliverPolicy;
        $this->ackPolicy = $ackPolicy;
    }

    protected function run(): bool
    {
        Co::define($this->name.'_JetstreamConsumerProcess'.$this->subject.'_'.$this->streamName.'_'.Str::random())
            ->charge(function () {
                $driver = new NatsDriver($this->subject.'_'.$this->streamName.'_'.Str::random()); //todo check working with singleton, but maybe change to separated connections
                $driver->subscribeToJetstreamWithEndpoint(
                    subject: $this->subject,
                    streamName: $this->streamName,
                    endpoint: $this->endpoint,
                    periodInMicroseconds: $this->periodInMicroseconds,
                    disableSpatieValidation: $this->disableSpatieValidation,
                    deliverPolicy: $this->deliverPolicy,
                    ackPolicy: $this->ackPolicy
                );
            })->runWithClonedDiContainer();
        return false;
    }
}
