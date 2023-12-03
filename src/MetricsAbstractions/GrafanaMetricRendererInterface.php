<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions;

interface GrafanaMetricRendererInterface
{
    /**
     * This method returns an array that can be used to render a Grafana metric inside dashboard.
     * @return array
     */
    public function renderMetric(): array;
    public function renderAlerts(): string;
}
