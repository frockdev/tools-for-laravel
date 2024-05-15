<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions;

use FrockDev\ToolsForLaravel\MetricsAbstractions\Renderers\CurrentNumberRenderer;
use Illuminate\Foundation\Application;

abstract class AbstractMetric
{
    const METRIC_NAME = '';
    const DESCRIPTION = '';
    const ROW_NAME = '';
    const BOARD_NAME = '';
    const LABEL_NAMES = [];
    const BUCKETS = [0.005, 0.01, 0.02, 0.04, 0.08, 0.15, 0.25, 0.50, 0.75, 1, 1.5, 3, 5, 8, 15];
    const FORMULAS = [];
    const RATE_BY = '1m';
    const RENDERER = CurrentNumberRenderer::class;
    private Application $application;

    private function __construct()
    {
        $this->application = app();
    }

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
            return $metric;
        } else {
            return app()->get(static::METRIC_NAME.'.'.static::class);
        }
    }
}
