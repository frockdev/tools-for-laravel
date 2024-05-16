<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetrics;

use FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetric;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Controls\CounterControlTrait;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Renderers\CurrentNumberRenderer;

abstract class CurrentNumberMetric extends AbstractMetric
{
    use CounterControlTrait;
    const RENDERER = CurrentNumberRenderer::class;

    const GREEN_THRESHOLD = 3;
    const YELLOW_THRESHOLD = 2;
    const ORANGE_THRESHOLD = 1;
    const RED_THRESHOLD = 0;
}
