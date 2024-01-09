<?php

namespace FrockDev\ToolsForLaravel\HyperfProxies;

use Illuminate\Foundation\Application;
use Psr\Container\ContainerInterface;

/**
 * @deprecated
 */

class HyperfContainer implements ContainerInterface
{

    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function get(string $id)
    {
        try {
            if ($this->app->has($id)) {
                return $this->app->get($id);
            } else {
                return $this->app->make($id);
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function has(string $id): bool
    {
        return $this->app->has($id);
    }

    public function make(string $id, array $params = []) {
        return $this->app->make($id, $params);
    }
}
