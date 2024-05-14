<?php

namespace FrockDev\ToolsForLaravel\Swow\Logging;

use FrockDev\ToolsForLaravel\Swow\ContextStorage;

/**
 * Class LogContext
 * @static method addContext(string $key, mixed $value)
 * @static method cloneLogContextFromFirstCoroutineToSecond(int $firstCoroutineId, int $secondCoroutineId)
 * @static method getLogContext()
 * @method addContext(string $key, mixed $value)
 * @method cloneLogContextFromFirstCoroutineToSecond(int $firstCoroutineId, int $secondCoroutineId)
 * @method getLogContext()
 */
class LogContext
{

    private function __construct()
    {

    }

    private static $instance = null;
    public static function getInstance(): LogContext
    {
        if (self::$instance === null) {
            self::$instance = new LogContext();
        }
        return self::$instance;
    }

    public function __call($name, $arguments)
    {
        if ($name === 'addContext') {
            ContextStorage::addLogContext($arguments[0], $arguments[1]);
        }
        if ($name === 'cloneLogContextFromFirstCoroutineToSecond') {
            ContextStorage::cloneLogContextFromFirstCoroutineToSecond($arguments[0], $arguments[1]);
        }
        if ($name === 'getLogContext') {
            return ContextStorage::getLogContext();
        }
        return null;
    }

    public static function __callStatic($name, $arguments)
    {
        if ($name === 'addContext') {
            ContextStorage::addLogContext($arguments[0], $arguments[1]);
        }
        if ($name === 'cloneLogContextFromFirstCoroutineToSecond') {
            ContextStorage::cloneLogContextFromFirstCoroutineToSecond($arguments[0], $arguments[1]);
        }
        if ($name === 'getLogContext') {
            return ContextStorage::getLogContext();
        }
        return null;
    }

}