<?php

namespace FrockDev\ToolsForLaravel\Swow\Nats;

use Basis\Nats\Consumer\Configuration as ConsumerConfiguration;
use Basis\Nats\Stream\Configuration;
use Swow\Channel;

class SwowNatsStream
{
    private array $consumers = [];
    private readonly Configuration $configuration;

    public function __construct(public readonly NewNatsClient $client, string $name)
    {
        $this->configuration = new Configuration($name);
    }

    public function create(): self
    {
        $this->client->api("STREAM.CREATE." . $this->getName(), $this->configuration->toArray());

        return $this;
    }

    public function createIfNotExists(): self
    {
        if (!$this->exists()) {
            return $this->create();
        }
        return $this;
    }

    public function delete(): self
    {
        if ($this->exists()) {
            $this->client->api("STREAM.DELETE." . $this->getName());
        }

        return $this;
    }

    public function exists(): bool
    {
        return in_array($this->getName(), $this->client->getApi()->getStreamNames());
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function createEphemeralConsumer(ConsumerConfiguration $configuration): SwowNatsConsumer
    {
        $consumer = new SwowNatsConsumer($this->client, $configuration->ephemeral());
        $consumer->create();

        $this->consumers[$consumer->getName()] = $consumer;
        return $consumer;
    }

    public function getConsumer(string $name): SwowNatsConsumer
    {
        if (!array_key_exists($name, $this->consumers)) {
            $configuration = new ConsumerConfiguration($this->getName(), $name);
            $this->consumers[$name] = new SwowNatsConsumer($this->client, $configuration);
        }

        return $this->consumers[$name];
    }

    public function getConsumerNames(): array
    {
        $result = $this->client->api('CONSUMER.NAMES.' . $this->getName());
        return $result->consumers;
    }

    public function getLastMessage(string $subject)
    {
        return $this->client->api('STREAM.MSG.GET.' . $this->getName(), [
            'last_by_subj' => $subject
        ]);
    }

    public function getName(): string
    {
        return $this->configuration->getName();
    }

    public function info()
    {
        return $this->client->api("STREAM.INFO." . $this->getName());
    }

    public function put(string $subject, mixed $payload): self
    {
        $this->client->publish($subject, $payload);
        return $this;
    }

    public function publish(string $subject, mixed $payload)
    {
        return $this->client->dispatch($subject, $payload);
    }

    public function update()
    {
        $this->client->api("STREAM.UPDATE." . $this->getName(), $this->configuration->toArray());
    }
}

