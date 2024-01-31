<?php

namespace FrockDev\ToolsForLaravel\Swow\Nats;

use Basis\Nats\Client;
use Basis\Nats\KeyValue\Bucket;
use Basis\Nats\Stream\Stream;

class SwowNatsApi
{
    private array $streams = [];
    private array $buckets = [];

    public function __construct(public readonly SwowNatsClient $client)
    {
    }

    public function getBucket(string $name): Bucket
    {
        if (!array_key_exists($name, $this->buckets)) {
            $this->buckets[$name] = new SwowNatsBucket($this->client, $name);
        }

        return $this->buckets[$name];
    }

    public function getInfo()
    {
        return $this->client->api('INFO');
    }

    public function getStreamList(): array
    {
        return $this->client->api('STREAM.LIST')->streams ?: [];
    }

    public function getStreamNames(): array
    {
        return $this->client->api('STREAM.NAMES')->streams ?: [];
    }

    public function getStream(string $name): SwowNatsStream
    {
        if (!array_key_exists($name, $this->streams)) {
            $this->streams[$name] = new SwowNatsStream($this->client, $name);
        }

        return $this->streams[$name];
    }
}
