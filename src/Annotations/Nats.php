<?php

namespace FrockDev\ToolsForLaravel\Annotations;

use FrockDev\ToolsForLaravel\AnnotationSupport\NatsType;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Nats
{
    public NatsType $type;
    public string $subject;

    public function __construct(NatsType $type, string $subject)
    {
        $this->type = $type;
        $this->subject = $subject;
    }

}
