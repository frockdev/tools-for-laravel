<?php

namespace FrockDev\ToolsForLaravel\Annotations;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Cron
{
    public string $schedule;

    public function __construct(string $schedule)
    {
        $this->schedule = $schedule;
    }

}
