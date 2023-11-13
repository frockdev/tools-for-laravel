<?php
namespace FrockDev\ToolsForLaravel\Nats;

use FrockDev\ToolsForLaravel\Nats\Encoders\Encoder;

/**
 * Class EncodedConnection
 *
 * @package Nats
 */
class EncodedConnection extends Connection
{

    /**
     * Encoder for this connection.
     *
     * @var \FrockDev\ToolsForLaravel\Nats\Encoders\Encoder|null
     */
    private $encoder = null;


    /**
     * EncodedConnection constructor.
     *
     * @param ConnectionOptions           $options Connection options object.
     * @param \FrockDev\ToolsForLaravel\Nats\Encoders\Encoder|null $encoder Encoder to use with the payload.
     */
    public function __construct(ConnectionOptions $options = null, Encoder $encoder = null)
    {
        $this->encoder = $encoder;
        parent::__construct($options);
    }

    /**
     * Publish publishes the data argument to the given subject.
     *
     * @param string $subject Message topic.
     * @param mixed $payload Message data.
     * @param string $inbox Message inbox.
     * @param array $headers Message headers.
     * @return void
     * @throws \Exception
     */
    public function publish($subject, $payload = null, $inbox = null, $headers = [])
    {
        list($payload, $headers) = $this->encoder->encode($payload, $headers);
        parent::publish($subject, $payload, $inbox, $headers);
    }

    /**
     * @param string $subject
     * @param mixed $payload
     * @param \Closure $callback
     * @return void
     */
    public function request($subject, $payload, ?\Closure $callback = null, ?string $messageTypeForEncoder = null)
    {
        $returnMessage = null;
        $inbox = uniqid('_INBOX.');
        $sid   = $this->subscribe(
            $inbox,
            $callback ?? function (Message $message) use (&$returnMessage) {
                $returnMessage = $message;
            },
            $messageTypeForEncoder
        );
        $this->unsubscribe($sid, 1);
        $this->publish($subject, $payload, $inbox);
        $this->wait(1);
        return $returnMessage;
    }

    /**
     * Subscribes to an specific event given a subject.
     *
     * @param string   $subject  Message topic.
     * @param \Closure $callback Closure to be executed as callback.
     *
     * @return string
     */
    public function subscribe($subject, \Closure $callback, ?string $messageTypeForEncoder = null)
    {
        //todo need to see about headers
        $c = function (Message $message) use ($callback, $messageTypeForEncoder) {
            $message->setBody($this->encoder->decode($message->getBody(), [], $message->getSubject(), $messageTypeForEncoder));
            $callback($message);
        };
        return parent::subscribe($subject, $c);
    }

    /**
     * Subscribes to an specific event given a subject and a queue.
     *
     * @param string   $subject  Message topic.
     * @param string   $queue    Queue name.
     * @param \Closure $callback Closure to be executed as callback.
     *
     * @return void
     */
    public function queueSubscribe($subject, $queue, \Closure $callback)
    {
        $c = function (Message $message) use ($callback) {
            $message->setBody($this->encoder->decode($message->getBody(), [], $message->getSubject()));
            $callback($message);
        };
        parent::queueSubscribe($subject, $queue, $c);
    }
}
