<?php

namespace FrockDev\ToolsForLaravel\BaseMetrics;

use FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetrics\CountGraphMetric;

class EndpointCallsCountMetric extends CountGraphMetric
{
    const METRIC_NAME = 'endpoint_calls_count';

    const DESCRIPTION = 'Endpoint calls count';

    const BOARD_NAME = 'Merchant-PHP/Endpoint Calls';

    const RENDERER = 'FrockDev\ToolsForLaravel\MetricsAbstractions\Renderers\CountGraphRenderer';
    const ROW_NAME = 'Endpoint Calls Counts';

    const LABELS = ['endpoint'];
}
