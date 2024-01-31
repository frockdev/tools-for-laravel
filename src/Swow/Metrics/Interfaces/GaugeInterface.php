<?php

namespace FrockDev\ToolsForLaravel\Swow\Metrics\Interfaces;

interface GaugeInterface
{
    public function with(string ...$labelValues): static;

    public function set(float $value): void;

    public function add(float $delta): void;
}
