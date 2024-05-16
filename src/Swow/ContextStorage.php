<?php

namespace FrockDev\ToolsForLaravel\Swow;

use Illuminate\Foundation\Application;
use Swow\Channel;
use Swow\Coroutine;

class ContextStorage
{
    static private array $storage = [
        'systemChannels' => [],
        'containers' => [],
        'interStreamInstances' => [],
        'interStreamStrings' => [],
        'logContext'=>[]
    ];

    public static function setInterStreamString(string $value) {
        self::$storage['interStreamStrings'][$value] = $value;
    }

    public static function addLogContext($key, $value) {
        $coroutineId = \Swow\Coroutine::getCurrent()->getId();
        self::$storage['logContext'][$coroutineId][$key] = $value;
    }

    public static function getLogContext() {
        $coroutineId = \Swow\Coroutine::getCurrent()->getId();
        return self::$storage['logContext'][$coroutineId] ?? [];
    }

    public static function cloneLogContextFromFirstCoroutineToSecond(int $firstCoroutineId, int $secondCoroutineId) {
        if (!isset(self::$storage['logContext'][$firstCoroutineId])) {
            self::$storage['logContext'][$secondCoroutineId] = [];
            return;
        }
        self::$storage['logContext'][$secondCoroutineId] = self::$storage['logContext'][$firstCoroutineId];
    }

    public static function getInterStreamStrings() {
        return self::$storage['interStreamStrings'];
    }

    public static function setInterStreamInstance($abstract, $instance) {
        self::$storage['interStreamInstances'][$abstract] = $instance;
    }

    public static function getInterStreamInstances() {
        return self::$storage['interStreamInstances'];
    }

    public static function removeSystemChannel(string $name): void
    {
        unset(self::$storage['systemChannels'][$name]);
    }

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
        unset(self::$storage['routineNames'][$coroutineId]);
        unset(self::$storage['logContext'][$coroutineId]);
    }

    public static function setApplication(\Illuminate\Contracts\Container\Container|Application $application, int $coroutineId = null): void
    {
        $coroutineId = $coroutineId ?? \Swow\Coroutine::getCurrent()->getId();
        if (!self::getCurrentRoutineName()) {
            throw new \Exception('Routine name is not set');
        }
        self::$storage['routineNames'][$coroutineId] = self::getCurrentRoutineName();
        self::$storage['containers'][$coroutineId] = $application;
    }

    public static function setCurrentRoutineName(string $name): void
    {
        $coroutineId = \Swow\Coroutine::getCurrent()->getId();
        self::$storage['routineNames'][$coroutineId] = $name;
    }

    public static function getCurrentRoutineName() {
        $coroutineId = \Swow\Coroutine::getCurrent()->getId();
        return self::$storage['routineNames'][$coroutineId] ?? null;
    }

    public static function getMainApplication(): ?Application
    {
        $coroutineId = Coroutine::getMain()->getId();
        $mainApp = self::$storage['containers'][$coroutineId];
        if (!$mainApp) throw new \Exception('Main application not found');
        return $mainApp;
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
