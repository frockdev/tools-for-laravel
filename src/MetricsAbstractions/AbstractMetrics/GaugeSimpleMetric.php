<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetrics;

use FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetric;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Controls\GaugeControlTrait;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Renderers\GaugeSimpleRenderer;

abstract class GaugeSimpleMetric extends AbstractMetric
{
    use GaugeControlTrait;
    const RENDERER = GaugeSimpleRenderer::class;
}
