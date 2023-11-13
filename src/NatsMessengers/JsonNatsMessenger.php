<?php

namespace FrockDev\ToolsForLaravel\NatsMessengers;

class JsonNatsMessenger
{

    private \Basis\Nats\Client $client;

    public function __construct(\Basis\Nats\Client $client)
    {
        $this->client = $client;
    }

}
