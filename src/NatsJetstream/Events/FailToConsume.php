<?php

namespace FrockDev\ToolsForLaravel\NatsJetstream\Events;

class FailToConsume
{
    public array $data;
    private \Throwable $e;

    public function __construct(\Throwable $e, array $data = [])
    {
        $this->data = $data;
        $this->e = $e;
    }
}
