<?php

namespace FrockDev\ToolsForLaravel\MessageObjects;

use Google\Protobuf\Internal\Message;

class NatsMessageObject
{
    public string $subject;
    public ?string $replyTo;
    public Message $body;
    public string $sid;

    public string $traceId;
    public string $endpointClass;
    public string $endpointInputType;
    public string $endpointOutputType;

    public function __construct(string $subject, ?string $replyTo, Message $body, string $sid, string $endpointClass, string $endpointInputType, string $endpointOutputType, $traceId='')
    {
        $this->subject = $subject;
        $this->replyTo = $replyTo;
        $this->body = $body;
        $this->sid = $sid;
        $this->endpointClass = $endpointClass;
        $this->endpointInputType = $endpointInputType;
        $this->endpointOutputType = $endpointOutputType;
        if ($traceId=='') {
            $this->traceId = uniqid('phpTraceId-');
        }
    }
}
