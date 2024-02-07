<?php

namespace FrockDev\ToolsForLaravel\Swow\Processes;

use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use FrockDev\ToolsForLaravel\Swow\CoroutineManager;
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
        $allowedMemoryUsage = env('ALLOWED_MEMORY_USAGE', 200);
        $counterName = 'coroutine_count';
        CoroutineManager::runSafe(function () use ($counterName) {
            $coroutineGauge = $this->registry->getOrRegisterGauge(
                $counterName,
                'coroutine_count',
                'coroutine_count');
            while (true) {
                Liveness::setLiveness('coroutine_count_control', 200, 'controlling', Liveness::MODE_EACH);
                $coroutineGauge->set(Coroutine::count());
                sleep(1);
            }
        }, $this->getName().'_'.$counterName);

        $counterName = 'contextual_storage_count';
        CoroutineManager::runSafe(function () use ($counterName) {
            $coroutineGauge = $this->registry->getOrRegisterGauge(
                $counterName,
                'contextual_storage_count',
                'contextual_storage_count');
            while (true) {
                Liveness::setLiveness('contextual_storage_count_control', 200, 'controlling', Liveness::MODE_EACH);
                $coroutineGauge->set(ContextStorage::getStorageCountForMetric());
                sleep(1);
            }
        }, $this->getName().'_'.$counterName);

        $counterName = 'di_containers_count';
        CoroutineManager::runSafe(function () use ($counterName) {
            $coroutineGauge = $this->registry->getOrRegisterGauge(
                $counterName,
                'containers_count',
                'containers_count');
            while (true) {
                Liveness::setLiveness('containers_count_control', 200, 'controlling', Liveness::MODE_EACH);
                $coroutineGauge->set(ContextStorage::getContainersCountForMetric());
                sleep(1);
            }
        }, $this->getName().'_'.$counterName);

        $counterName = 'memory_usage';
        CoroutineManager::runSafe(function () use ($counterName) {
            $coroutineGauge = $this->registry->getOrRegisterGauge(
                $counterName,
                'memory_usage',
                'memory_usage');
            while (true) {
                Liveness::setLiveness('memory_usage_control', 200, 'controlling', Liveness::MODE_EACH);
                $coroutineGauge->set(memory_get_usage()/1024/1024);
                sleep(1);
            }
        }, $this->getName().'_'.$counterName);

        if (function_exists('gc_enabled') && gc_enabled()) {
            $counterName = 'gc_cycles_collected_count';
            CoroutineManager::runSafe(function () use ($counterName, $allowedMemoryUsage) {
                $coroutineGauge = $this->registry->getOrRegisterGauge(
                    $counterName,
                    'gc_enabled',
                    'gc_enabled');
                while (true) {
                    Liveness::setLiveness('gc_control', 200, 'controlling', Liveness::MODE_EACH);
                    if (memory_get_usage()/1024/1024 > $allowedMemoryUsage) {
                        $cycles = gc_collect_cycles();
                        $coroutineGauge->set($cycles);
                    }
                    sleep(30);
                }
            }, $this->getName().'_'.$counterName);
        }
    }
}
