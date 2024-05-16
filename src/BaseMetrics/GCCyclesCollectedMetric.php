<?php

namespace FrockDev\ToolsForLaravel\BaseMetrics;

use FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetrics\GaugeSimpleMetric;

class GCCyclesCollectedMetric extends GaugeSimpleMetric
{
    const METRIC_NAME = 'gc_cycles_forced';
    const DESCRIPTION = 'Garbage collection cycles collected by force';
    const BOARD_NAME = 'System Metrics';
    const ROW_NAME = 'Main metrics';
}