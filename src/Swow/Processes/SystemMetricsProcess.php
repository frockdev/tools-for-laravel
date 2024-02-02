<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\Swow\Liveness\Liveness;
use Prometheus\CollectorRegistry;
use Swow\Coroutine;

class SystemMetricsProcess extends AbstractProcess
{

    public function __construct(private CollectorRegistry $registry)
    {
    }

    protected function run(): void
    {
        Coroutine::run(function () {
            $coroutineGauge = $this->registry->getOrRegisterGauge(
                'coroutine_count',
                'coroutine_count',
                'coroutine_count');
            while (true) {
                Liveness::setLiveness('coroutine_count_control', 200, 'controlling', Liveness::MODE_EACH);
                $coroutineGauge->set(Coroutine::count());
                sleep(1);
            }
        });
    }
}
