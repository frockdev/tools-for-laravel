<?php

namespace FrockDev\ToolsForLaravel\Nats\Messengers;


use FrockDev\ToolsForLaravel\Nats\EncodedConnection;
use Google\Protobuf\Internal\Message;

class GrpcNatsMessenger
{
    private EncodedConnection $connection;

    public function __construct(EncodedConnection $jobs)
    {
        $this->connection = $jobs;
        if (!config('nats.autoconnectDisabled')==true) {
            $this->connection->connect();
        }
    }

    public function sendMessageToChannel(string $channel, Message $message)
    {
        $this->connection->publish($channel, $message);
    }

    public function sendMessageAsRequest(string $channel, Message $message, string $messageTypeToDecode): Message
    {
        /** @var \FrockDev\ToolsForLaravel\Nats\Message $result */
        $result = $this->connection->request($channel, $message, null, $messageTypeToDecode);
        return $result->getBody();
    }

    public function subscribeAsAQueueWorker(string $channel, callable $callback)
    {
        $this->connection->queueSubscribe($channel, $channel, $callback);
    }

    public function subscribeAsChannelSubscriber(string $channel, callable $callback) {
        $this->connection->subscribe($channel, $callback);
    }

    public function waitNMessages(int $n) {
        $this->connection->wait($n);
    }

}
