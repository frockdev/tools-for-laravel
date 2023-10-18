<?php

namespace FrockDev\ToolsForLaravel\InterceptorInterfaces;

use Spiral\RoadRunner\GRPC\ContextInterface;

interface PreInterceptorInterface
{
    public function intercept(array &$ctx, \Google\Protobuf\Internal\Message &$in): void;
}
