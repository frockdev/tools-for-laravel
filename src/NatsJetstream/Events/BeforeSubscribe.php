<?php

namespace FrockDev\ToolsForLaravel\NatsJetstream\Events;

class BeforeSubscribe
{
    public array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }
}
