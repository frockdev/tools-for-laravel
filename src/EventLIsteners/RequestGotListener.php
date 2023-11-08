<?php

namespace FrockDev\ToolsForLaravel\EventLIsteners;

use FrockDev\ToolsForLaravel\Events\BeforeEndpointCalled;
use FrockDev\ToolsForLaravel\Events\RequestGot;
use Illuminate\Support\Facades\Log;

/**
 * This class will reset framework context on each request.
 */
class RequestGotListener
{
    public function handle(RequestGot $event): void {
        // On start working on request flush everything, for example trace_ids.

    }
}
