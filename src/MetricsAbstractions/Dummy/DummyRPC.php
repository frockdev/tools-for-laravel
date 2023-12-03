<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\Dummy;

use Spiral\Goridge\RPC\CodecInterface;
use Spiral\Goridge\RPC\RPCInterface;

class DummyRPC implements RPCInterface
{

    public function withServicePrefix(string $service): RPCInterface
    {
        return $this;
    }

    public function withCodec(CodecInterface $codec): RPCInterface
    {
        return $this;
    }

    public function call(string $method, mixed $payload, mixed $options = null): mixed
    {
        return null;
    }
}
