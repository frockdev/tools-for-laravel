<?php

namespace FrockDev\ToolsForLaravel\PreInterceptors;

use Attribute;
use FrockDev\ToolsForLaravel\InterceptorInterfaces\PreInterceptorInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

#[Attribute(Attribute::TARGET_METHOD)]
class TraceIdToLogs implements PreInterceptorInterface
{
    public function intercept(array &$ctx, \Google\Protobuf\Internal\Message &$in): void
    {
        $header = config('frock.traceIdCtxHeader', 'X-Trace-ID');
        if (array_key_exists($header, $ctx)) {
            $traceId = $ctx[$header];
        } else {
            $traceId = Str::uuid()->toString();
        }
        Log::shareContext(array_filter([
            'trace_id' => $traceId,
        ]));
    }
}
