<?php

namespace FrockDev\ToolsForLaravel\Console;

use FrockDev\ToolsForLaravel\AnnotationsCollector\Collector;
use Illuminate\Console\Command;

class CollectAttributesToCache extends Command
{
    protected $signature = 'frock:collect-attributes-to-cache';
    public function handle(Collector $collector)
    {
        $collector->collect();
        $this->info('Collected');
    }
}
