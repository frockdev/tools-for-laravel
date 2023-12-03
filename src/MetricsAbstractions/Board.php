<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions;

class Board
{
    private string $boardName;
    private string $boardPath;

    public function __construct(string $boardName, string $boardPath)
    {
        $this->boardName = $boardName;
        $this->boardPath = $boardPath;
    }
}
