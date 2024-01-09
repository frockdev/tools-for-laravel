<?php

namespace FrockDev\ToolsForLaravel\HyperfProxies;

use Hyperf\Contract\ConfigInterface;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Log;

/**
 * @deprecated
 */
class HyperfConfig implements ConfigInterface
{

    private Repository $config;

    public function __construct(Repository $config)
    {
        $this->config = $config;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }

    public function has(string $keys): bool
    {
        return $this->config->has($keys);
    }

    public function set(string $key, mixed $value): void
    {
        $this->config->set($key, $value);
    }
}
