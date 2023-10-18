<?php

namespace FrockDev\ToolsForLaravel\InterceptorInterfaces;

use Spiral\RoadRunner\GRPC\ContextInterface;

interface PostInterceptorInterface
{
    public function intercept(array &$ctx, \Google\Protobuf\Internal\Message &$in, \Google\Protobuf\Internal\Message &$out): void;
}
