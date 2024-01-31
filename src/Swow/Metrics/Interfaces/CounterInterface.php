<?php

namespace FrockDev\ToolsForLaravel\Swow\Metrics\Interfaces;

interface CounterInterface
{
    public function with(string ...$labelValues): static;

    public function add(int $delta): void;
}
