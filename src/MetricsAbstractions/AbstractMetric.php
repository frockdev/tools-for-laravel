<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions;

use FrockDev\ToolsForLaravel\MetricsAbstractions\Renderers\CurrentNumberRenderer;
use Spiral\RoadRunner\Metrics\Metrics;

abstract class AbstractMetric
{
    const METRIC_NAME = '';
    const DESCRIPTION = '';
    const ROW_NAME = '';
    const BOARD_NAME = '';
    const LABELS = [];
    const BUCKETS = [];
    const FORMULAS = [];
    const RATE_BY = '1m';
    const RENDERER = CurrentNumberRenderer::class;

    private function __construct()
    {

    }

    abstract public function register(Metrics $metrics);

    /**
     * @internal
     */
    public static function getInstanceForRender(): static {
        return new static();
    }

    public static function declare(): static {
        if (!app()->bound(static::METRIC_NAME.'.'.static::class)) {
            app()->singleton(static::METRIC_NAME.'.'.static::class, static::class);
            $metric = new static();
            app()->instance(static::METRIC_NAME.'.'.static::class, $metric);
            $metric->register(app()->make(Metrics::class));
            return $metric;
        } else {
            return app()->make(static::METRIC_NAME.'.'.static::class);
        }
    }
}
