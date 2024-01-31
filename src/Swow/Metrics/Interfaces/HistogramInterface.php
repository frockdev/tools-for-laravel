<?php

namespace FrockDev\ToolsForLaravel\Swow\Metrics\Interfaces;

interface HistogramInterface
{
    public function with(string ...$labelValues): static;

    public function put(float $sample): void;
}
