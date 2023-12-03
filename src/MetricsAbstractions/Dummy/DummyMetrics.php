<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\Dummy;

use Spiral\Goridge\RPC\Exception\ServiceException;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Metrics\CollectorInterface;
use Spiral\RoadRunner\Metrics\Exception\MetricsException;
use Spiral\RoadRunner\Metrics\Metrics;

class DummyMetrics extends Metrics
{
    private const SERVICE_NAME = 'metrics';

    private readonly RPCInterface $rpc;

    public function __construct(RPCInterface $rpc)
    {
        $this->rpc = $rpc->withServicePrefix(self::SERVICE_NAME);
        parent::__construct($this->rpc);
    }

    public function add(string $name, float $value, array $labels = []): void
    {
        return;
    }

    public function sub(string $name, float $value, array $labels = []): void
    {
        return;
    }

    public function observe(string $name, float $value, array $labels = []): void
    {
        return;
    }

    public function set(string $name, float $value, array $labels = []): void
    {
        return;
    }

    public function declare(string $name, CollectorInterface $collector): void
    {
        return;
    }

    public function unregister(string $name): void
    {
        return;
    }
}
