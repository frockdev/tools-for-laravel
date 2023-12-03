<?php

namespace FrockDev\ToolsForLaravel\BaseMetrics;

use FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetrics\TimeHistogramMetric;

class EndpointCallsDurationMetric extends TimeHistogramMetric
{
    const METRIC_NAME = 'endpoint_calls_duration';

    const DESCRIPTION = 'Endpoint calls duration';

    const BUCKETS = [10,15,20,25,30,35,40,45,50,70,100,150,250,350,500,700,1000,2000,5000,10000,20000,30000,50000,70000,100000];

    const BOARD_NAME = 'Merchant-PHP/Endpoint Calls';

    const RENDERER = 'FrockDev\ToolsForLaravel\MetricsAbstractions\Renderers\DurationGraphRenderer';
    const ROW_NAME = 'Endpoint Calls Durations';

    const LABELS = ['endpoint'];
}
