<?php

namespace FrockDev\ToolsForLaravel\Swow\Metrics;

use Prometheus\CollectorRegistry;

class MetricFactory implements MetricFactoryInterface
{

    private \Prometheus\CollectorRegistry $registry;
    /**
     * @var array|\Illuminate\Config\Repository|\Illuminate\Contracts\Foundation\Application|\Illuminate\Foundation\Application|mixed|string|string[]
     */
    private string $appNameConverted;

    public function __construct(CollectorRegistry $registry)
    {
        $this->appNameConverted = str_replace(['.', '-'], '_', config('app.name'));
        $this->registry = $registry;
    }

    public function makeCounter(string $name, array $labelNames = [], string $help=''): \Prometheus\Counter
    {
        return $this->registry->getOrRegisterCounter($this->appNameConverted, $name, 'it increases', $labelNames);
    }

    public function makeGauge(string $name, array $labelNames = [], string $help=''): \Prometheus\Gauge
    {
        return $this->registry->getOrRegisterGauge($this->appNameConverted, $name, $help, $labelNames);
    }

    public function makeHistogram(string $name, array $labelNames = [], string $help='', $quants=[0.1, 1, 2, 3.5, 4, 5, 6, 7, 8, 9]): \Prometheus\Histogram
    {
        return $this->registry->getOrRegisterHistogram($this->appNameConverted, $name, $help, $labelNames, $quants);
    }

    public function makeSummary(string $name, array $labelNames = [], string $help='', $quants=[0.01, 0.05, 0.5, 0.95, 0.99], int $maxAgeSeconds = 84600): \Prometheus\Summary
    {
        return $this->registry->getOrRegisterSummary($this->appNameConverted, $name, $help, ['type'], $maxAgeSeconds, $quants);
    }
}
