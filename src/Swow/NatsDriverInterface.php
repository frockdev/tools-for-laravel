<?php

namespace FrockDev\ToolsForLaravel\Swow;


use Basis\Nats\Consumer\AckPolicy;
use Basis\Nats\Consumer\DeliverPolicy;
use FrockDev\ToolsForLaravel\Transport\AbstractMessage;
use Swow\Sync\WaitGroup;

interface NatsDriverInterface
{

    public function __construct(string $name);

    public function runReceiving(string $namePostfix=''): WaitGroup;

    public function publishToStream(string $streamName, string $subject, string|AbstractMessage $payload);

    public function publish(string $subject, string|AbstractMessage $payload, $replyTo = null): void;

    public function publishSync(string $subject, string|AbstractMessage $payload, ?string $decodeTo=null): string|AbstractMessage;

    public function runThroughKernel(string $subject, string $body, array $headers = [], ?string $queue=null, ?string $stream=null): \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\Response;

    public function subscribeToJetstreamWithEndpoint(string $subject, string $streamName, object $endpoint, $periodInMicroseconds=null, $disableSpatieValidation = false, $deliverPolicy = DeliverPolicy::NEW, $ackPolicy = AckPolicy::NONE);

    public function subscribeWithEndpoint(string $subject, object $endpoint, ?string $queueName=null, bool $disableSpatieValidation = false);
}
