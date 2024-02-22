<?php

namespace FrockDev\ToolsForLaravel\Annotations;

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

    public function __construct(
        string  $subject,
        string  $streamName,
        ?string $queue = null,
        ?string $name = 'unnamed',
        int     $nums = 1,
        ?string $pool = null,
        ?int    $periodInMicroseconds = null,
    )
    {
        $this->subject = $subject;
        $this->streamName = $streamName;
        $this->queue = $queue;
        $this->name = $name;
        $this->nums = $nums;
        $this->pool = $pool;
        $this->periodInMicroseconds = $periodInMicroseconds;
    }

}
