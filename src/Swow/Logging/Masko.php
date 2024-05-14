<?php

namespace FrockDev\ToolsForLaravel\Swow\Logging;

use FrockDev\ToolsForLaravel\Swow\ContextStorage;

/**
 * Class Masko
 * @static method maskForLogging(string $value)
 * @method maskForLogging(string $value)
 */
class Masko
{
    private function __construct()
    {

    }

    private static $instance = null;
    public static function getInstance(): Masko
    {
        if (self::$instance === null) {
            self::$instance = new Masko();
        }
        return self::$instance;
    }

    public function __call($name, $arguments)
    {
        if ($name === 'maskForLogging') {
            ContextStorage::setInterStreamString($arguments[0]);
        }
    }

    public static function __callStatic($name, $arguments)
    {
        if ($name === 'maskForLogging') {
            ContextStorage::setInterStreamString($arguments[0]);
        }
    }

    /**
     * @return array|mixed
     * @deprecated
     */
    public static function getInterStreamStrings()
    {
        return ContextStorage::getInterStreamStrings();
    }

}