<?php
namespace FrockDev\ToolsForLaravel\Nats\Encoders;

/**
 * Class JSONEncoder
 *
 * Encodes and decodes messages in JSON format.
 *
 * @package Nats
 */
class JSONEncoder implements Encoder
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
        $payload = json_encode($payload);
        return [$payload, $headers];
    }

    /**
     * Decodes a message.
     *
     *
     * @param string $payload
     * @param array $headers
     * @param null $subject
     * @return mixed
     */
    public function decode($payload, $headers = [], $subject = null)
    {
        $payload = json_decode($payload, true);
        return $payload;
    }
}
