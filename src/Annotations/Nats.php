<?php

namespace FrockDev\ToolsForLaravel\Annotations;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Nats
{
    public string $subject;
    public ?string $queueGroup = null;

    public ?string $stream = null;
    public ?string $consumerName = null;

    public function __construct(string $subject, ?string $queueGroup = null, ?string $stream = null, ?string $consumerName = null)
    {
        $this->subject = $subject;
        $this->stream = $stream;
        $this->consumerName = $consumerName;
        $this->queueGroup = $queueGroup;
    }

}
