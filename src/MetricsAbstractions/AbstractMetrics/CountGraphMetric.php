<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetrics;

use FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetric;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Controls\CounterControlTrait;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Renderers\CountGraphRenderer;
use Spiral\RoadRunner\Metrics\Collector;
use Spiral\RoadRunner\Metrics\Metrics;

abstract class CountGraphMetric extends AbstractMetric
{
    use CounterControlTrait;
    const RENDERER = CountGraphRenderer::class;
    public function register(Metrics $metrics)
    {
        $metrics->declare(static::METRIC_NAME,
            Collector::counter()
                ->withHelp(static::DESCRIPTION)
                ->withLabels(...static::LABELS)
        );
    }
}
