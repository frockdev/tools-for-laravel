<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\Controls;

use FrockDev\ToolsForLaravel\Swow\Metrics\MetricFactoryInterface;
use Prometheus\Histogram;

trait HistogramControlTrait
{
    public function observe(float $value, array $labels = []) {
        if (!empty($labels)) {
            $this->getCounter()->observe($value, $labels);
        } else {
            $this->getCounter()->observe($value);
        }
    }

    private function getCounter(): Histogram {
        try {
            $instanceOrNull = app()->get('metric-'.static::METRIC_NAME);
        } catch (\Illuminate\Container\EntryNotFoundException $e) {
            /** @var MetricFactoryInterface $factory */
            $factory = app()->make(MetricFactoryInterface::class);
            $instanceOrNull = $factory->makeHistogram(static::METRIC_NAME, static::LABEL_NAMES, static::DESCRIPTION, static::BUCKETS);
            app()->instance('metric-'.static::METRIC_NAME, $instanceOrNull);
        }
        return $instanceOrNull;
    }

}
