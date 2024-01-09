<?php

namespace FrockDev\ToolsForLaravel\NatsMessengers;

use Basis\Nats\Client;
use Basis\Nats\Message\Msg;
use FrockDev\ToolsForLaravel\MessageObjects\NatsMessageObject;
use Google\Protobuf\Internal\Message;

class GrpcNatsMessenger
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param array $natsSubjectConfig
     * @param callable $callback
     * @return void
     * @deprecated
     */
    public function subscribeToJetStream(array $natsSubjectConfig, callable $callback)
    {
        $function = function (Msg $natsMsg) use ($callback, $natsSubjectConfig) {
            /** @var Message $grpcObject */
            $grpcObject = new ($natsSubjectConfig['inputType'])();
            $grpcObject->mergeFromJsonString($natsMsg->payload->body);

            $result = new NatsMessageObject(
                $natsMsg->subject,
                $natsMsg->replyTo,
                $grpcObject,
                $natsMsg->sid,
                $natsSubjectConfig['endpoint'],
                $natsSubjectConfig['inputType'],
                $natsSubjectConfig['outputType'],
                array_key_exists(config('nats.traceIdHeader', 'traceId'), $natsMsg->payload->headers)
                    ? $natsMsg->payload->headers[config('nats.traceIdHeader', 'traceId')]
                    : ''
                //todo should be traceId when we walk through HSUB and have headers over NATS
            );

            $callback($result);
        };
        $jetStream = $this->client->getApi()->getStream($natsSubjectConfig['streamName']);
        $consumer = $jetStream->getConsumer($natsSubjectConfig['consumerName']);
        $consumer->getConfiguration()->setSubjectFilter($natsSubjectConfig['subject']);
        $consumer->handle($function);
    }

    public function sendMessageToChannel(string $channel, Message $message)
    {
        $this->client->publish($channel, $message->serializeToJsonString());
    }

    /**
     * still internal, because have no way to test scenarios correctly
     * @internal
     * @param string $channel
     * @param Message $message
     * @param string $messageTypeToDecode
     * @return Message
     */
    public function sendMessageAsRequest(string $channel, Message $message, string $messageTypeToDecode): Message
    {
        $result = null;
        $this->client->request($channel, $message->serializeToJsonString(), function (Msg $message) use (&$result, $messageTypeToDecode) {
            /** @var Message $result */
            $result = new $messageTypeToDecode();
            $result->mergeFromJsonString($message->payload->body);
        });
        return $result;
    }

    /**
     * @param array $natsSubjectConfig
     * @param callable $callback
     * @return void
     * @deprecated
     */
    public function subscribeAsChannelSubscriber(array $natsSubjectConfig, callable $callback)
    {
        $function = function (Msg $natsMsg) use ($callback, $natsSubjectConfig) {
            /** @var Message $grpcObject */
            $grpcObject = new ($natsSubjectConfig['outputType'])();
            $grpcObject->mergeFromJsonString($natsMsg->payload->body);

            $result = new NatsMessageObject(
                $natsMsg->subject,
                $natsMsg->replyTo,
                $grpcObject,
                $natsMsg->sid,
                $natsSubjectConfig['endpoint'],
                $natsSubjectConfig['inputType'],
                $natsSubjectConfig['outputType'],
                array_key_exists(config('nats.traceIdHeader', 'traceId'), $natsMsg->payload->headers)
                    ? $natsMsg->payload->headers[config('nats.traceIdHeader', 'traceId')]
                    : ''
            //todo should be traceId when we walk through HSUB and have headers over NATS
            );

            $callback($result);
        };
        $this->client->subscribe( $natsSubjectConfig['subject'], $function);
    }

    /**
     * @param array $natsSubjectConfig
     * @param callable $callback
     * @return void
     * @deprecated
     */
    public function subscribeAsQueueSubscriber(array $natsSubjectConfig, callable $callback)
    {
        $function = function (Msg $natsMsg) use ($callback, $natsSubjectConfig) {
            /** @var Message $grpcObject */
            $grpcObject = new ($natsSubjectConfig['inputType'])();
            $grpcObject->mergeFromJsonString($natsMsg->payload->body);

            $result = new NatsMessageObject(
                $natsMsg->subject,
                $natsMsg->replyTo,
                $grpcObject,
                $natsMsg->sid,
                $natsSubjectConfig['endpoint'],
                $natsSubjectConfig['inputType'],
                $natsSubjectConfig['outputType'],
                array_key_exists(config('nats.traceIdHeader', 'traceId'), $natsMsg->payload->headers)
                    ? $natsMsg->payload->headers[config('nats.traceIdHeader', 'traceId')]
                    : ''
            //todo should be traceId when we walk through HSUB and have headers over NATS
            );

            $callback($result);
        };
        $this->client->subscribeQueue( $natsSubjectConfig['subject'], $natsSubjectConfig['queueGroup'] ?? $natsSubjectConfig['subject'], $function);
    }

    public function process()
    {
        $this->client->process($this->client->configuration->timeout);
    }

}
