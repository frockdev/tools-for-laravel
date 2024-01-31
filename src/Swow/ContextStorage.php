<?php

namespace FrockDev\ToolsForLaravel\Swow;

use Swow\Channel;

class ContextStorage
{
    static private array $storage = [];

    public static function setSystemChannel(string $name, Channel $channel): void
    {
        if (array_key_exists($name, self::$storage['systemChannels'] ?? [])) {
            throw new \Exception(sprintf('Channel with name %s already exists', $name));
        }
        self::$storage['systemChannels'][$name] = $channel;
    }

    public static function getSystemChannel(string $name): Channel
    {
        if (!array_key_exists($name, self::$storage['systemChannels'] ?? [])) {
            throw new \Exception(sprintf('Channel with name %s does not exist', $name));
        }
        return self::$storage['systemChannels'][$name];
    }

    static public function set(string $key, $value): void
    {
        $coroutineId = \Swow\Coroutine::getCurrent()->getId();
        self::$storage[$coroutineId][$key] = $value;
    }

    static public function get(string $key)
    {
        $coroutineId = \Swow\Coroutine::getCurrent()->getId();
        return self::$storage[$coroutineId][$key] ?? null;
    }

    public static function dump(string $message = '')
    {
        $coroutineId = \Swow\Coroutine::getCurrent()->getId();
        var_dump($message, self::$storage[$coroutineId]??[]);
    }

    public static function clearStorage(): void
    {
        $coroutineId = \Swow\Coroutine::getCurrent()->getId();
        unset(self::$storage[$coroutineId]);
    }
}
