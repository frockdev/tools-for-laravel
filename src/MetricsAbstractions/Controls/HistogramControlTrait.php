<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\Controls;

use Spiral\RoadRunner\Metrics\Metrics;

trait HistogramControlTrait
{
    public function observe(float $value, array $labels = []) {
        /** @var Metrics $metrics */
        $metrics = app()->make(Metrics::class);
        $metrics->observe(static::METRIC_NAME, $value, $labels);
    }

}
