<?php

namespace FrockDev\ToolsForLaravel\InterceptorInterfaces;

interface PostInterceptorInterface
{
    public function intercept(array &$ctx, \Google\Protobuf\Internal\Message &$in, \Google\Protobuf\Internal\Message &$out): void;
}
