<?php

namespace FrockDev\ToolsForLaravel\Swow\Logging;

use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Monolog\Formatter\JsonFormatter;

class ValuesMaskJsonFormatter extends JsonFormatter
{
    public function normalize(mixed $data, int $depth = 0): mixed
    {
        $normalized = parent::normalize($data, $depth);
        foreach (ContextStorage::getInterStreamStrings() as $value) {
            if (is_string($normalized)) {
                $normalized = str_replace($value, '******', $normalized);
            }
        }
        return $normalized;
    }
}