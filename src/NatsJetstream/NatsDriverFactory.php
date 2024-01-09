<?php

namespace FrockDev\ToolsForLaravel\NatsJetstream;

use Psr\Container\ContainerInterface;

class NatsDriverFactory
{

    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function get(string $poolName): NatsJetstreamGrpcDriver
    {
        return new NatsJetstreamGrpcDriver($this->container, $poolName);
    }
}
