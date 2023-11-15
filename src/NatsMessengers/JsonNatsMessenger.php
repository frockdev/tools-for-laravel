<?php

namespace FrockDev\ToolsForLaravel\NatsMessengers;

use Basis\Nats\Message\Msg;

class JsonNatsMessenger
{

    private \Basis\Nats\Client $client;

    public function __construct(\Basis\Nats\Client $client)
    {
        $this->client = $client;
    }

    public function sendMessageToChannel(string $channel, array $message)
    {
        $this->client->publish($channel, json_encode($message));
    }

    /**
     * still internal, because have no way to test scenarios correctly
     * @param string $channel
     * @param array $message
     * @param string $messageTypeToDecode
     * @return array
     * @internal
     */
    public function sendMessageAsRequest(string $channel, array $message): array
    {
        $result = null;
        $this->client->request($channel, json_encode($message), function (Msg $message) use (&$result) {
            $result = json_decode($message->payload->body, true);
        });
        return $result;
    }

    public function subscribeAsChannelSubscriber(string $channel, callable $callback)
    {
        $function = function (Msg $natsMsg) use ($callback) {
            $callback(json_decode($natsMsg->payload->body, true));
        };
        $this->client->subscribe( $channel, $function);
    }

    public function process()
    {
        $this->client->process($this->client->configuration->timeout);
    }

}
