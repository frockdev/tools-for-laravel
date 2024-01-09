<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetrics;

use FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetric;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Controls\CounterControlTrait;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Renderers\CountGraphRenderer;
use Hyperf\Metric\Contract\CounterInterface;
use Hyperf\Metric\Contract\MetricFactoryInterface as Metrics;

abstract class CountGraphMetric extends AbstractMetric
{
    use CounterControlTrait;
    const RENDERER = CountGraphRenderer::class;
}
