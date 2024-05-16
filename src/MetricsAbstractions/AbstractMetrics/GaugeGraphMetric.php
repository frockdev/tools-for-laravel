<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetrics;

use FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetric;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Controls\GaugeControlTrait;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Renderers\GaugeGraphRenderer;

abstract class GaugeGraphMetric extends AbstractMetric
{
    use GaugeControlTrait;
    const RENDERER = GaugeGraphRenderer::class;
}
