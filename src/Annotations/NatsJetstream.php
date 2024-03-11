<?php

namespace FrockDev\ToolsForLaravel\Annotations;

use Basis\Nats\Consumer\AckPolicy;
use Basis\Nats\Consumer\DeliverPolicy;

#[\Attribute(\Attribute::TARGET_CLASS)]
class NatsJetstream
{
    public string $streamName;

    public ?string $name = 'unnamedJetstream';

    public string $subject;

    public ?string $queue = null;
    public int $nums = 1;

    public ?string $pool = null;

    public ?int $periodInMicroseconds = null;

    public string $deliverPolicy = DeliverPolicy::NEW;

    public string $ackPolicy = AckPolicy::NONE;

    public function __construct(
        string  $subject,
        string  $streamName,
        ?string $queue = null,
        ?string $name = 'unnamed',
        int     $nums = 1,
        ?string $pool = null,
        ?int    $periodInMicroseconds = null,
        ?string $deliverPolicy = DeliverPolicy::NEW,
        ?string $ackPolicy = AckPolicy::NONE
    )
    {
        $this->subject = $subject;
        $this->streamName = $streamName;
        $this->queue = $queue;
        $this->name = $name;
        $this->nums = $nums;
        $this->pool = $pool;
        $this->periodInMicroseconds = $periodInMicroseconds;
        $this->deliverPolicy = $deliverPolicy;
        $this->ackPolicy = $ackPolicy;
    }

}
