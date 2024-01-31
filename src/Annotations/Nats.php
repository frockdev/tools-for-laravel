<?php

namespace FrockDev\ToolsForLaravel\Annotations;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Nats
{
    public string $subject;

    public ?string $queueName = null;

    public ?string $name = 'unnamed';

    public function __construct(
        string  $subject,
        ?string $queueName = null,
        ?string $name = 'unnamed',
    )
    {
        $this->subject = $subject;
        $this->queueName = $queueName;
        $this->name = $name;
    }
}
