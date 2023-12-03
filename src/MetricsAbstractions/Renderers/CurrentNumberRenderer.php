<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\Renderers;

use FrockDev\ToolsForLaravel\MetricsAbstractions\AbstractMetrics\CurrentNumberMetric;
use FrockDev\ToolsForLaravel\MetricsAbstractions\GrafanaMetricRendererInterface;
use Illuminate\Support\Facades\Blade;

class CurrentNumberRenderer implements GrafanaMetricRendererInterface
{
    private CurrentNumberMetric $metric;

    public function __construct(CurrentNumberMetric $metric)
    {
        $this->metric = $metric;
    }

    public function renderMetric(): array
    {
        $templateString = file_get_contents(app_path().'/../vendor/frock-dev/tools-for-laravel/metricTemplates/counter.simple.blade.json');
        $rendered = Blade::render($templateString,
            [
                'metricName' => $this->metric::METRIC_NAME,
                'redThreshold' => $this->metric::RED_THRESHOLD,
                'orangeThreshold' => $this->metric::ORANGE_THRESHOLD,
                'yellowThreshold' => $this->metric::YELLOW_THRESHOLD,
                'greenThreshold' => $this->metric::GREEN_THRESHOLD,
                'rateBy' => $this->metric::RATE_BY,
                'applicationName' => config('app.name'),
            ]
        );
        return json_decode($rendered, true);
    }

    public function renderAlerts(): string
    {
        return '';
    }
}
