<?php

namespace FrockDev\ToolsForLaravel\MetricsAbstractions;

class Row
{
    public string $rowName;
    private Board $board;

    public function __construct(string $rowName, Board $board)
    {
        $this->rowName = $rowName;
        $this->board = $board;
    }
}
