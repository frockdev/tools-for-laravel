<?php

namespace FrockDev\ToolsForLaravel\Annotations;

use FrockDev\ToolsForLaravel\AnnotationSupport\NatsType;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Nats
{
    public string $subject;

    public function __construct(string $subject)
    {
        $this->subject = $subject;
    }

}
