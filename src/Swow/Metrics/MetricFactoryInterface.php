<?php

namespace FrockDev\ToolsForLaravel\Swow\Metrics;

interface MetricFactoryInterface
{
    public function makeCounter(string $name, array $labelNames = [], string $help=''): \Prometheus\Counter;
    public function makeGauge(string $name, array $labelNames = [], string $help=''): \Prometheus\Gauge;
    public function makeHistogram(string $name, array $labelNames = [], string $help='', $quants=[0.1, 1, 2, 3.5, 4, 5, 6, 7, 8, 9]): \Prometheus\Histogram;
    public function makeSummary(string $name, array $labelNames = [], string $help='', $quants=[0.01, 0.05, 0.5, 0.95, 0.99], int $maxAgeSeconds = 84600): \Prometheus\Summary;
}
