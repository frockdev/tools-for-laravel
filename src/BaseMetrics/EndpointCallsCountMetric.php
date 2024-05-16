<?php

namespace FrockDev\ToolsForLaravel\BaseMetrics;

use FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetrics\CountRateMetric;

class EndpointCallsCountMetric extends CountRateMetric
{
    const METRIC_NAME = 'endpoint_calls_count';
    const DESCRIPTION = 'Endpoint calls count';
    const BOARD_NAME = 'Endpoint Calls';
    const ROW_NAME = 'Endpoint Calls Counts';
    const LABEL_NAMES = ['endpoint'];
}
