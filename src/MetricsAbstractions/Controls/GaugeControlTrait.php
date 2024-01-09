<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions\Controls;

use Hyperf\Metric\Contract\GaugeInterface;
use Hyperf\Metric\Contract\MetricFactoryInterface;

trait GaugeControlTrait
{
    public function inc(array $labels = []) {
        $this->add(1, $labels);
    }

    public function dec(array $labels = []) {
        $this->sub(1, $labels);
    }

    public function add(float $value, array $labels = []) {
        if (!empty($labels)) {
            $this->getCounter()->with(...$labels)->add($value);
        } else {
            $this->getCounter()->add($value);
        }
    }

    public function sub(float $value, array $labels = []) {
        if (!empty($labels)) {
            $this->getCounter()->with(...$labels)->add(-$value);
        } else {
            $this->getCounter()->add(-$value);
        }
    }

    public function set(float $value, array $labels = []) {
        if (!empty($labels)) {
            $this->getCounter()->with(...$labels)->set($value);
        } else {
            $this->getCounter()->set($value);
        }
    }

    private function getCounter() {
        try {
            $instanceOrNull = app()->get('metric-'.static::METRIC_NAME);
        } catch (\Throwable $e) {
            /** @var MetricFactoryInterface $factory */
            $factory = app()->get(\Hyperf\Nano\App::class)->getContainer()->get(MetricFactoryInterface::class);
            $instanceOrNull = $factory->makeGauge(static::METRIC_NAME, static::LABEL_NAMES);
            app()->instance('metric-'.static::METRIC_NAME, $instanceOrNull);
        }

        return $instanceOrNull;
    }

}
