<?php

namespace FrockDev\ToolsForLaravel\Swow\Liveness;

class Liveness
{
    public const MODE_EACH = 'each';
    public const MODE_ONCE = 'once';
    public const MODE_5_SEC = 'each-5-seconds';
    public const MODE_1_SEC = 'each-1-second';

    public const MODE_EACH_5_TRY = 'each-5-try';

    public const MODE_EACH_10_TRY = 'each-10-try';

    public static function setLiveness(string $componentName, int $componentState, string $componentMessage, string $mode = self::MODE_EACH): void
    {
        /** @var Storage $storage */
        $storage = app()->make(Storage::class);
        $storage->setLiveness($componentName, $componentState, $componentMessage, $mode);
    }
}
