<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\Controls;

use FrockDev\ToolsForLaravel\Swow\Metrics\MetricFactoryInterface;
use Prometheus\Counter;

trait CounterControlTrait
{
    public function inc(array $labels = []) {
        $this->add(1, $labels);
    }
    public function add(int $points, array $labels = []) {
        if (!empty($labels)) {
            $this->getCounter()->incBy($points, $labels);
        } else {
            $this->getCounter()->incBy($points);
        }
    }

    private function getCounter(): Counter {
        try {
            $instanceOrNull = app()->get('metric-'.static::METRIC_NAME);
        } catch (\Throwable $e) {
            /** @var MetricFactoryInterface $factory */
            $factory = app()->make(MetricFactoryInterface::class);
            $instanceOrNull = $factory->makeCounter(static::METRIC_NAME, static::LABEL_NAMES);
            app()->instance('metric-'.static::METRIC_NAME, $instanceOrNull);
        }

        return $instanceOrNull;
    }
}
