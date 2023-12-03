<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\Controls;

use Spiral\RoadRunner\Metrics\Metrics;

trait GaugeControlTrait
{
    public function inc(array $labels = []) {
        $this->add(1, $labels);
    }

    public function dec(array $labels = []) {
        $this->sub(1, $labels);
    }

    public function add(float $value, array $labels = []) {
        /** @var Metrics $metrics */
        $metrics = app()->make(Metrics::class);
        $metrics->add(static::METRIC_NAME, $value, $labels);
    }

    public function sub(float $value, array $labels = []) {
        /** @var Metrics $metrics */
        $metrics = app()->make(Metrics::class);
        $metrics->sub(static::METRIC_NAME, $value, $labels);
    }

    public function set(float $value, array $labels = []) {
        /** @var Metrics $metrics */
        $metrics = app()->make(Metrics::class);
        $metrics->set(static::METRIC_NAME, $value, $labels);
    }

}
