<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\Controls;

use Hyperf\Metric\Contract\MetricFactoryInterface;

trait CounterControlTrait
{
    public function inc(array $labels = []) {
        $this->add(1, $labels);
    }
    public function add(int $points, array $labels = []) {
        if (!empty($labels)) {
            $this->getCounter()->with(...$labels)->add($points);
        } else {
            $this->getCounter()->add($points);
        }
    }

    private function getCounter() {
        try {
            $instanceOrNull = app()->get('metric-'.static::METRIC_NAME);
        } catch (\Throwable $e) {
            /** @var MetricFactoryInterface $factory */
            $factory = app()->get(\Hyperf\Nano\App::class)->getContainer()->get(MetricFactoryInterface::class);
            $instanceOrNull = $factory->makeCounter(static::METRIC_NAME, static::LABEL_NAMES);
            app()->instance('metric-'.static::METRIC_NAME, $instanceOrNull);
        }

        return $instanceOrNull;
    }
}
