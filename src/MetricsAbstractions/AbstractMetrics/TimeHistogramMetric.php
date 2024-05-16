<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetrics;

use FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetric;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Controls\HistogramControlTrait;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Renderers\DurationGraphRenderer;

abstract class TimeHistogramMetric extends AbstractMetric
{
    use HistogramControlTrait;

    const RENDERER = DurationGraphRenderer::class;

}
