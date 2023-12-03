<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\Controls;

use Spiral\RoadRunner\Metrics\Metrics;

trait CounterControlTrait
{
    public function inc(array $labels = []) {
        $this->add(1, $labels);
    }
    public function add(int $points, array $labels = []) {
        /** @var Metrics $metrics */
        $metrics = app()->make(Metrics::class);
        $metrics->add(static::METRIC_NAME, $points, $labels);
    }
}
