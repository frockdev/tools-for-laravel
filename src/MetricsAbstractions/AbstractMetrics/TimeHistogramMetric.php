<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetrics;

use FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetric;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Controls\HistogramControlTrait;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Renderers\DurationGraphRenderer;
use Spiral\RoadRunner\Metrics\Collector;
use Spiral\RoadRunner\Metrics\Metrics;

abstract class TimeHistogramMetric extends AbstractMetric
{
    use HistogramControlTrait;

    const RENDERER = DurationGraphRenderer::class;

    public function register(Metrics $metrics)
    {
        $metrics->declare(static::METRIC_NAME,
            Collector::histogram(...static::BUCKETS)
                ->withHelp(static::DESCRIPTION)
                ->withLabels(...static::LABELS)
        );
    }

}
