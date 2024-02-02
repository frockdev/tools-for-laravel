<?php

namespace FrockDev\ToolsForLaravel\BaseMetrics;

use FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetrics\TimeHistogramMetric;

class EndpointCallsDurationMetric extends TimeHistogramMetric
{
    const METRIC_NAME = 'endpoint_calls_duration';

    const DESCRIPTION = 'Endpoint calls duration';

    const BUCKETS = [0.005, 0.01, 0.02, 0.04, 0.08, 0.15, 0.25, 0.50, 0.75, 1, 1.5, 3, 5, 8, 15];

    const BOARD_NAME = 'Endpoint Calls';

    const RENDERER = 'FrockDev\ToolsForLaravel\MetricsAbstractions\Renderers\DurationGraphRenderer';
    const ROW_NAME = 'Endpoint Calls Durations';

    const LABEL_NAMES = ['endpoint'];
}
