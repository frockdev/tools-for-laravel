<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetrics;

use FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetric;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Controls\CounterControlTrait;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Renderers\CountGraphRenderer;

abstract class CountGraphMetric extends AbstractMetric
{
    use CounterControlTrait;
    const RENDERER = CountGraphRenderer::class;
}
