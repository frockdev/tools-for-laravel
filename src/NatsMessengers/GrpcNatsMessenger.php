<?php

namespace FrockDev\ToolsForLaravel\NatsMessengers;

use Basis\Nats\Message\Msg;
use Google\Protobuf\Internal\Message;

class GrpcNatsMessenger
{


    private \Basis\Nats\Client $client;

    public function __construct(\Basis\Nats\Client $client)
    {
        $this->client = $client;
    }

    public function subscribeToJetStream(string $streamName, string $subject, string $consumerName, callable $callback, string $messageTypeToDecode)
    {
        $function = function (Msg $message) use ($callback, $messageTypeToDecode) {
            /** @var Message $result */
            $result = new $messageTypeToDecode();
            $result->mergeFromJsonString($message->payload->body);
            $callback($result);
        };
        $jetStream = $this->client->getApi()->getStream($streamName);
        $consumer = $jetStream->getConsumer($consumerName);
        $consumer->getConfiguration()->setSubjectFilter($subject);
        $consumer->handle($callback);
    }

    public function sendMessageToChannel(string $channel, Message $message)
    {
        $this->client->publish($channel, $message);
    }

    public function sendMessageAsRequest(string $channel, Message $message, string $messageTypeToDecode): Message
    {
        $result = null;
        $this->client->request($channel, $message->serializeToJsonString(), function (Msg $message) use ($result, $messageTypeToDecode) {
            /** @var Message $result */
            $result = new $messageTypeToDecode();
            $result->mergeFromJsonString($message->payload->body);
        });
        return $result;
    }

    /** Here in callback we must pass message as Google Proto Message */
    public function subscribeAsAQueueWorker(string $channel, callable $callback, string $messageTypeToDecode)
    {
        $function = function (Msg $message) use ($callback, $messageTypeToDecode) {
            /** @var Message $result */
            $result = new $messageTypeToDecode();
            $result->mergeFromJsonString($message->payload->body);
            $callback($result);
        };
        $this->client->subscribeQueue($channel, $channel, $function);
    }

    public function subscribeAsChannelSubscriber(string $channel, callable $callback, string $messageTypeToDecode)
    {
        $function = function (Msg $message) use ($callback, $messageTypeToDecode) {
            /** @var Message $result */
            $result = new $messageTypeToDecode();
            $result->mergeFromJsonString($message->payload->body);
            $callback($result);
        };
        $this->client->subscribe($channel, $function);
    }

    public function waitNMessages(int $n)
    {
        throw new \Exception('Now there is no sense');
    }

}
