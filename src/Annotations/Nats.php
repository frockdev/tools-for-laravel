<?php

namespace FrockDev\ToolsForLaravel\Annotations;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Nats
{
    public string $subject;

    public function __construct(string $subject)
    {
        $this->subject = $subject;
    }

}
