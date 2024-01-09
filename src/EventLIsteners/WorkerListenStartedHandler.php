<?php

namespace FrockDev\ToolsForLaravel\EventLIsteners;

use FrockDev\ToolsForLaravel\Events\BeforeRequestProcessedEvent;
use FrockDev\ToolsForLaravel\Events\RequestGot;
use FrockDev\ToolsForLaravel\Events\WorkerListenStarted;
use Illuminate\Support\Facades\Log;

/**
 * This class will reset framework context on each request.
 * @deprecated
 */
class WorkerListenStartedHandler
{
    public function handle(WorkerListenStarted $event): void {
        // On start working on request flush everything, for example trace_ids.
//        Log::flushSharedContext();
//        Log::withoutContext()->debug('Shared Context Flushing');
    }
}
