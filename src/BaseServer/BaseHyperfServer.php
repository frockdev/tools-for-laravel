<?php

namespace FrockDev\ToolsForLaravel\BaseServer;

use Hyperf\Contract\MiddlewareInitializerInterface;
use Hyperf\Server\Event;
use Swoole\Server as SwooleServer;
use Swoole\Server\Port as SwoolePort;

class BaseHyperfServer extends \Hyperf\Server\Server
{

    protected function registerSwooleEvents(SwoolePort|SwooleServer $server, array $events, string $serverName): void
    {
        foreach ($events as $event => $callback) {
            if (! Event::isSwooleEvent($event)) {
                continue;
            }
            if (is_array($callback)) {
                [$className, $method] = $callback;
                if (array_key_exists($className . $method, $this->onRequestCallbacks)) {
                    $this->logger->warning(sprintf('%s will be replaced by %s. Each server should have its own onRequest callback. Please check your configs.', $this->onRequestCallbacks[$className . $method], $serverName));
                }

                $this->onRequestCallbacks[$className . $method] = $serverName;
                $class = $this->container->get($className); // возможно здесь нам понадобится make
                if (method_exists($class, 'setServerName')) {
                    // Override the server name.
                    $class->setServerName($serverName);
                }
                if ($class instanceof MiddlewareInitializerInterface) {
                    $class->initCoreMiddleware($serverName);
                }
                $callback = [$class, $method];
            }
            $server->on($event, $callback);
        }
    }

}
