<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\BaseMetrics\GCCyclesCollectedMetric;
use FrockDev\ToolsForLaravel\BaseMetrics\MemoryRAMGaugeSimpleMetric;
use FrockDev\ToolsForLaravel\Swow\Co\Co;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use FrockDev\ToolsForLaravel\Swow\Liveness\Liveness;
use Prometheus\CollectorRegistry;
use Swow\Coroutine;

class SystemMetricsProcess extends AbstractProcess
{

    public function __construct(private CollectorRegistry $registry)
    {
    }

    protected function run(): bool
    {
        $allowedMemoryUsage = env('ALLOWED_MEMORY_USAGE', 200);
        $counterName = 'coroutine_count';
        Co::define($this->getName() . '_' . $counterName)
            ->charge(function () use ($counterName) {
                $coroutineGauge = $this->registry->getOrRegisterGauge(
                    $counterName,
                    'coroutine_count',
                    'coroutine_count');
                while (true) {
                    Liveness::setLiveness('coroutine_count_control', 200, 'controlling', Liveness::MODE_EACH);
                    $coroutineGauge->set(Coroutine::count());
                    sleep(1);
                }
            })->runWithClonedDiContainer();

        $counterName = 'contextual_storage_count';
        Co::define($this->getName() . '_' . $counterName)
            ->charge(function () use ($counterName) {
                $coroutineGauge = $this->registry->getOrRegisterGauge(
                    $counterName,
                    'contextual_storage_count',
                    'contextual_storage_count');
                while (true) {
                    Liveness::setLiveness('contextual_storage_count_control', 200, 'controlling', Liveness::MODE_EACH);
                    $coroutineGauge->set(ContextStorage::getStorageCountForMetric());
                    sleep(1);
                }
            })->runWithClonedDiContainer();

        $counterName = 'di_containers_count';
        Co::define($this->getName() . '_' . $counterName)
            ->charge(function () use ($counterName) {
                $coroutineGauge = $this->registry->getOrRegisterGauge(
                    $counterName,
                    'containers_count',
                    'containers_count');
                while (true) {
                    Liveness::setLiveness('containers_count_control', 200, 'controlling', Liveness::MODE_EACH);
                    $coroutineGauge->set(ContextStorage::getContainersCountForMetric());
                    sleep(1);
                }
            })->runWithClonedDiContainer();

        $counterName = 'memory_usage';
        Co::define($this->getName() . '_' . $counterName)
            ->charge(function () use ($counterName) {
                $coroutineGauge = MemoryRAMGaugeSimpleMetric::declare();
                while (true) {
                    Liveness::setLiveness('memory_usage_control', 200, 'controlling', Liveness::MODE_EACH);
                    $coroutineGauge->set(memory_get_usage() / 1024 / 1024);
                    sleep(1);
                }
            })->runWithClonedDiContainer();

        if (function_exists('gc_enabled') && gc_enabled()) {
            $counterName = 'gc_cycles_collected_count';
            Co::define($this->getName() . '_' . $counterName)
                ->charge(function () use ($counterName, $allowedMemoryUsage) {
                    $coroutineGauge = GCCyclesCollectedMetric::declare();
                    while (true) {
                        Liveness::setLiveness('gc_control', 200, 'controlling', Liveness::MODE_EACH);
                        if (memory_get_usage() / 1024 / 1024 > $allowedMemoryUsage) {
                            $cycles = gc_collect_cycles();
                            $coroutineGauge->set($cycles);
                        } else {
                            $coroutineGauge->set(0);
                        }
                        sleep(30);
                    }
                })->runWithClonedDiContainer();
        }

        if (env('EXIT_ON_OOM')==1) {
            Co::define($this->getName() . '_OOM_EXITER')
                ->charge(function () use ($allowedMemoryUsage) {
                    while (true) {
                        if (memory_get_usage() / 1024 / 1024 > $allowedMemoryUsage) {
                            ContextStorage::getSystemChannel('exitChannel')->push(\Swow\Signal::TERM);
                        }
                        sleep(30);
                    }
                })->runWithClonedDiContainer();
        }

        return false;
    }
}
