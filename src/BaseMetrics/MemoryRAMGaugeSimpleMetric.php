<?php

namespace FrockDev\ToolsForLaravel\BaseMetrics;

use FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetrics\GaugeSimpleMetric;

class MemoryRAMGaugeSimpleMetric extends GaugeSimpleMetric
{
    const METRIC_NAME = 'ram_size';
    const DESCRIPTION = 'RAM size';
    const BOARD_NAME = 'System Metrics';
    const ROW_NAME = 'Main metrics';
}