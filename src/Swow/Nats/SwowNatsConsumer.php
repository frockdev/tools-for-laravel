<?php

namespace FrockDev\ToolsForLaravel\Swow\Nats;

use Basis\Nats\Consumer\Configuration;
use Basis\Nats\Consumer\Runtime;
use Closure;
use Swow\Channel;

class SwowNatsConsumer
{
    private ?bool $exists = null;
    private bool $interrupt = false;
    private float $delay = 1;
    private float $expires = 0.1;
    private int $batch = 1;
    private int $iterations = PHP_INT_MAX;

    public function __construct(
        public readonly NewNatsClient $client,
        private readonly Configuration $configuration,
    ) {
    }

    public function create($ifNotExists = true): self
    {
        if ($this->shouldCreateConsumer($ifNotExists)) {
            $command = $this->configuration->isEphemeral() ?
                'CONSUMER.CREATE.' . $this->getStream() :
                'CONSUMER.DURABLE.CREATE.' . $this->getStream() . '.' . $this->getName();

            $result = $this->client->api($command, $this->configuration->toArray());
            if ($result->error) {
                throw new \Exception('Consumer creation failed: ', ['natsError'=>$result->error]);
            }
            if ($this->configuration->isEphemeral()) {
                $this->configuration->setName($result->getName());
            }

            $this->exists = true;
        }

        return $this;
    }

    public function delete(): self
    {
        $this->client->api('CONSUMER.DELETE.' . $this->getStream() . '.' . $this->getName());
        $this->exists = false;

        return $this;
    }

    public function exists(): bool
    {
        if ($this->exists !== null) {
            return $this->exists;
        }
        $consumers = $this->client->getApi()->getStream($this->getStream())->getConsumerNames();
        return $this->exists = in_array($this->getName(), $consumers);
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function getName(): string
    {
        return $this->getConfiguration()->getName();
    }

    public function getStream(): string
    {
        return $this->getConfiguration()->getStream();
    }

    public function getBatching(): int
    {
        return $this->batch;
    }

    public function getDelay(): float
    {
        return $this->delay;
    }

    public function getExpires(): float
    {
        return $this->expires;
    }

    public function getIterations(): int
    {
        return $this->iterations;
    }

    public function handle(Closure $handler): int
    {
        $requestSubject = '$JS.API.CONSUMER.MSG.NEXT.' . $this->getStream() . '.' . $this->getName();

        $handlerSubject = 'handler.' . bin2hex(random_bytes(4));

        $this->create();
        $responsesCount = 0;
        $channelForResponsesCount = new Channel($this->getIterations());
        $this->client->subscribe($handlerSubject, function ($message) use ($handler, $channelForResponsesCount) {
            $channelForResponsesCount->push(1);
            if (!$message->isEmpty()) {
                $handler($message);
            }
        });

        $iteration = $this->getIterations();
        while ($iteration--) {

            $this->client->publish($requestSubject, [], $handlerSubject);
            $channelForResponsesCount->pop();
            $responsesCount++;
        }

        $this->client->unsubscribe($handlerSubject);

        return $responsesCount;
    }

    public function info()
    {
        return $this->client->api("CONSUMER.INFO." . $this->getStream() . '.' . $this->getName());
    }

    public function interrupt()
    {
        throw new \Exception('Not implemented interruption');
        $this->interrupt = true;
    }

    public function setBatching(int $batch): self
    {
        throw new \Exception('Not implemented batching');
        $this->batch = $batch;

        return $this;
    }

    public function setDelay(float $delay): self
    {
        $this->delay = $delay;

        return $this;
    }

    public function setExpires(float $expires): self
    {
        $this->expires = $expires;

        return $this;
    }

    public function setIterations(int $iterations): self
    {
        $this->iterations = $iterations;

        return $this;
    }

    private function shouldCreateConsumer(bool $ifNotExists): bool
    {
        return ($this->configuration->isEphemeral() && $this->configuration->getName() === null)
            || !$this->exists();
    }
}
