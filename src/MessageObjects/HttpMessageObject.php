<?php

namespace FrockDev\ToolsForLaravel\MessageObjects;

use Google\Protobuf\Internal\Message;

/**
 * @deprecated
 */
class HttpMessageObject
{
    public Message $body;
    public string $traceId='';
    public string $endpointClass;
    public string $endpointInputType;
    public string $endpointOutputType;

    public function __construct(Message $body, string $endpointClass, string $endpointInputType, string $endpointOutputType, string $traceId='')
    {
        $this->body = $body;
        $this->endpointClass = $endpointClass;
        $this->endpointInputType = $endpointInputType;
        $this->endpointOutputType = $endpointOutputType;
        if ($traceId=='') {
            $this->traceId = uuid_create();
        }
    }
}
