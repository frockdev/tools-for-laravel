<?php

namespace FrockDev\ToolsForLaravel\InterceptorInterfaces;

interface PreInterceptorInterface
{
    public function intercept(array &$ctx, \Google\Protobuf\Internal\Message &$in): void;
}
