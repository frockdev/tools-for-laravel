<?php

namespace FrockDev\ToolsForLaravel\EventLIsteners;

use FrockDev\ToolsForLaravel\Events\BeforeEndpointCalled;
use Illuminate\Support\Facades\Log;

class BeforeEndpointCalledListener
{
    public function handle(BeforeEndpointCalled $event): void {
        // On start working on request flush everything, for example trace_ids.
        Log::flushSharedContext();
    }
}
