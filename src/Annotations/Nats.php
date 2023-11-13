<?php

namespace FrockDev\ToolsForLaravel\Annotations;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Nats
{
    public string $subject;

    public ?string $stream = null;

    public function __construct(string $subject, ?string $stream = null)
    {
        $this->subject = $subject;
        $this->stream = $stream;
    }

}
