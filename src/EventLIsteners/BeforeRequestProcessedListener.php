<?php

namespace FrockDev\ToolsForLaravel\EventLIsteners;

use FrockDev\ToolsForLaravel\Events\BeforeRequestProcessedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class BeforeRequestProcessedListener implements ShouldQueue
{
    public function handle(BeforeRequestProcessedEvent $event) {
        // On start working on request flush everything

        return true;
    }
}
