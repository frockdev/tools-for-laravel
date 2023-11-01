<?php
namespace FrockDev\ToolsForLaravel\Nats\Encoders;

/**
 * Class JSONEncoder
 *
 * Encodes and decodes messages in JSON format.
 *
 * @package Nats
 */
class GRPCEncoder implements Encoder
{


    /**
     * Encodes a message.
     *
     * @param string $payload Message to decode.
     * @param array $headers
     *
     * @return array - encoded payload and headers values
     */
    public function encode($payload, $headers = [])
    {
        if (!$payload instanceof \Google\Protobuf\Internal\Message) {
            throw new \Exception('Payload must be a protobuf message');
        }
        $payload = $payload->serializeToJsonString();
        return [$payload, $headers];
    }

    /**
     * Decodes a message.
     *
     * @param string $payload Message to decode.
     * @param array $headers
     *
     * @return mixed
     */
    public function decode($payload, $headers = [], $subject = null, ?string $messageTypeForEncoder = null)
    {
        if ($subject===null) {
            throw new \Exception('Subject must be provided to decode a protobuf message');
        }

        if ($messageTypeForEncoder!==null) {
            /** @var \Google\Protobuf\Internal\Message $payload */
            $object = new $messageTypeForEncoder();
            $object->mergeFromJsonString($payload);
            return $object;
        }
        $payloadInfo = config('natsEndpoints.'.$subject);
        $type = $payloadInfo['inputType'];
        /** @var \Google\Protobuf\Internal\Message $payload */
        $object = new $type();
        $object->mergeFromJsonString($payload);
        return $object;
    }
}
