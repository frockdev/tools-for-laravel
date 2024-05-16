<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\Renderers;

use FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetric;
use FrockDev\ToolsForLaravel\MetricsAbstractions\GrafanaMetricRendererInterface;
use Illuminate\Support\Facades\Blade;

class GaugeSimpleRenderer implements GrafanaMetricRendererInterface
{
    private AbstractMetric $metric;

    public function __construct(AbstractMetric $metric)
    {
        $this->metric = $metric;
    }

    public function renderMetric(): array
    {
        $templateString = file_get_contents(app_path().'/../vendor/frock-dev/tools-for-laravel/metricTemplates/gauge.simple.blade.json');
        $rendered = Blade::render($templateString,
            [
                'metricName' => $this->metric::METRIC_NAME,
                'rateBy' => $this->metric::RATE_BY,
                'applicationName' => str(str_replace('-', '_', config('app.name'))),
            ]
        );
        return json_decode($rendered, true);
    }

    public function renderAlerts(): string
    {
        return '';
    }
}
