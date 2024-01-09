<?php

namespace FrockDev\ToolsForLaravel\Servers;

use FrockDev\ToolsForLaravel\Middlewares\HttpProtobufCoreMiddleware;
use Hyperf\HttpServer\Contract\CoreMiddlewareInterface;
use function Hyperf\Support\make;

class HttpProtobufServer extends \Hyperf\HttpServer\Server
{
    protected function createCoreMiddleware(): CoreMiddlewareInterface
    {
        return make(HttpProtobufCoreMiddleware::class, ['container'=>$this->container, 'serverName'=>$this->serverName]);
    }
}
