<?php

namespace FrockDev\ToolsForLaravel\Swow;

use Illuminate\Foundation\Application;
use Swow\Channel;

class ContextStorage
{
    static private array $storage = [
        'systemChannels' => [],
        'containers' => [],
    ];

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
        unset(self::$storage['containers'][$coroutineId]);
    }

    public static function setApplication(\Illuminate\Contracts\Container\Container|Application $application): void
    {
        $coroutineId = \Swow\Coroutine::getCurrent()->getId();
        self::$storage['containers'][$coroutineId] = $application;
    }

    public static function getApplication(): ?Application
    {
        $coroutineId = \Swow\Coroutine::getCurrent()->getId();
        return self::$storage['containers'][$coroutineId] ?? null;
    }

    public static function getStorageCountForMetric(): int
    {
        return count(self::$storage)-2;
    }

    public static function getContainersCountForMetric(): int
    {
        return count(self::$storage['containers']);
    }
}
