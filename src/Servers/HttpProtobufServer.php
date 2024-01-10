<?php

namespace FrockDev\ToolsForLaravel\Servers;

use FrockDev\ToolsForLaravel\Middlewares\HttpProtobufCoreMiddleware;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Contract\CoreMiddlewareInterface;
use Hyperf\HttpServer\Exception\Handler\HttpExceptionHandler;
use function Hyperf\Support\make;

class HttpProtobufServer extends \Hyperf\HttpServer\Server
{
    protected function createCoreMiddleware(): CoreMiddlewareInterface
    {
        return make(HttpProtobufCoreMiddleware::class, ['container'=>$this->container, 'serverName'=>$this->serverName]);
    }

    public function initCoreMiddleware(string $serverName): void
    {
        $this->serverName = $serverName;
        $this->coreMiddleware = $this->createCoreMiddleware();

        $config = $this->container->get(ConfigInterface::class);
        $this->middlewares = $config->get('middlewares.' . $serverName, []);
        $this->exceptionHandlers = $this->getDefaultExceptionHandler();

        $this->initOption();
    }

    protected function getDefaultExceptionHandler(): array
    {
        return [
            \FrockDev\ToolsForLaravel\ExceptionHandlers\HttpExceptionHandler::class,
        ];
    }
}
