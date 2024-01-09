<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\Controls;

use Hyperf\Metric\Contract\MetricFactoryInterface;
use Prometheus\CollectorRegistry;


trait HistogramControlTrait
{
    public function observe(float $value, array $labels = []) {
        if (!empty($labels)) {
            $this->getCounter()->with(...$labels)->put($value);
        } else {
            $this->getCounter()->put($value);
        }
    }

    private function getCounter() {
        try {
            $instanceOrNull = app()->get('metric-'.static::METRIC_NAME);
        } catch (\Illuminate\Container\EntryNotFoundException $e) {
            /** @var CollectorRegistry $registry */
            $registry = app()->get(\Hyperf\Nano\App::class)->getContainer()->get(CollectorRegistry::class);
            $registry->registerHistogram(
                config("metric.metric.prometheus.namespace"),
                static::METRIC_NAME,
                static::DESCRIPTION,
                static::LABEL_NAMES,
                static::BUCKETS
            );
            /** @var MetricFactoryInterface $factory */
            $factory = app()->get(\Hyperf\Nano\App::class)->getContainer()->get(MetricFactoryInterface::class);
            $instanceOrNull = $factory->makeHistogram(static::METRIC_NAME, static::LABEL_NAMES);
            app()->instance('metric-'.static::METRIC_NAME, $instanceOrNull);
        }
        return $instanceOrNull;
    }

}
