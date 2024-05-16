<?php

namespace FrockDev\ToolsForLaravel\BaseMetrics;

use FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetrics\GaugeGraphMetric;

class MemoryRAMGaugeMetric extends GaugeGraphMetric
{
    const METRIC_NAME = 'ram_size';
    const DESCRIPTION = 'RAM size';
    const BOARD_NAME = 'System Metrics';
    const ROW_NAME = 'Main metrics';
    const RENDERER = 'FrockDev\ToolsForLaravel\MetricsAbstractions\Renderers\GaugeGraphRenderer';
}