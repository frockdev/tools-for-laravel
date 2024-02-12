<?php

namespace FrockDev\ToolsForLaravel\Application;

use Closure;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;

class Application extends \Illuminate\Foundation\Application
{
    protected $safeContainerInitializationMode = false;
    public function __construct($basePath = null, $safeContainerInitialization = false)
    {
        if ($safeContainerInitialization) {
            $this->safeContainerInitializationMode = true;
        }
        parent::__construct($basePath);
    }

    public function bound($abstract)
    {
        if ($this->safeContainerInitializationMode) {
            return parent::bound($abstract);
        } else {
            return ContextStorage::getApplication()->bound($abstract);
        }
    }

    public function alias($abstract, $alias)
    {
        if ($this->safeContainerInitializationMode) {
            parent::alias($abstract, $alias);
        } else {
            ContextStorage::getApplication()->alias($abstract, $alias);
        }
    }

    public function tag($abstracts, $tags)
    {
        if ($this->safeContainerInitializationMode) {
            parent::tag($abstracts, $tags);
        } else {
            ContextStorage::getApplication()->tag($abstracts, $tags);
        }
    }

    public function tagged($tag)
    {
        if ($this->safeContainerInitializationMode) {
            return parent::tagged($tag);
        } else {
            return ContextStorage::getApplication()->tagged($tag);
        }
    }

    public function bind($abstract, $concrete = null, $shared = false)
    {
        if ($this->safeContainerInitializationMode) {
            parent::bind($abstract, $concrete, $shared);
        } else {
            ContextStorage::getApplication()->bind($abstract, $concrete, $shared);
        }
    }

    public function bindMethod($method, $callback)
    {
        if ($this->safeContainerInitializationMode) {
            parent::bindMethod($method, $callback);
        } else {
            ContextStorage::getApplication()->bindMethod($method, $callback);
        }
    }

    public function bindIf($abstract, $concrete = null, $shared = false)
    {
        if ($this->safeContainerInitializationMode) {
            parent::bindIf($abstract, $concrete, $shared);
        } else {
            ContextStorage::getApplication()->bindIf($abstract, $concrete, $shared);
        }
    }

    public function singleton($abstract, $concrete = null)
    {
        if ($this->safeContainerInitializationMode) {
            parent::singleton($abstract, $concrete);
        } else {
            ContextStorage::getApplication()->singleton($abstract, $concrete);
        }
    }

    public function singletonIf($abstract, $concrete = null)
    {
        if ($this->safeContainerInitializationMode) {
            parent::singletonIf($abstract, $concrete);
        } else {
            ContextStorage::getApplication()->singletonIf($abstract, $concrete);
        }
    }

    public function scoped($abstract, $concrete = null)
    {
        if ($this->safeContainerInitializationMode) {
            parent::scoped($abstract, $concrete);
        } else {
            ContextStorage::getApplication()->scoped($abstract, $concrete);
        }
    }

    public function scopedIf($abstract, $concrete = null)
    {
        if ($this->safeContainerInitializationMode) {
            parent::scopedIf($abstract, $concrete);
        } else {
            ContextStorage::getApplication()->scopedIf($abstract, $concrete);
        }
    }

    public function extend($abstract, Closure $closure)
    {
        if ($this->safeContainerInitializationMode) {
            parent::extend($abstract, $closure);
        } else {
            ContextStorage::getApplication()->extend($abstract, $closure);
        }
    }

    public function instance($abstract, $instance)
    {
        if ($this->safeContainerInitializationMode) {
            parent::instance($abstract, $instance);
        } else {
            ContextStorage::getApplication()->instance($abstract, $instance);
        }
    }

    public function addContextualBinding($concrete, $abstract, $implementation)
    {
        if ($this->safeContainerInitializationMode) {
            parent::addContextualBinding($concrete, $abstract, $implementation);
        } else {
            ContextStorage::getApplication()->addContextualBinding($concrete, $abstract, $implementation);
        }
    }

    public function when($concrete)
    {
        if ($this->safeContainerInitializationMode) {
            return parent::when($concrete);
        } else {
            return ContextStorage::getApplication()->when($concrete);
        }
    }

    public function factory($abstract)
    {
        if ($this->safeContainerInitializationMode) {
            return parent::factory($abstract);
        } else {
            return ContextStorage::getApplication()->factory($abstract);
        }
    }

    public function flush()
    {
        if ($this->safeContainerInitializationMode) {
            parent::flush();
        } else {
            ContextStorage::getApplication()->flush();
        }
    }

    public function make($abstract, array $parameters = [])
    {
        if ($this->safeContainerInitializationMode) {
            return parent::make($abstract, $parameters);
        } else {
            return ContextStorage::getApplication()->make($abstract, $parameters);
        }
    }

    public function call($callback, array $parameters = [], $defaultMethod = null)
    {
        if ($this->safeContainerInitializationMode) {
            return parent::call($callback, $parameters, $defaultMethod);
        } else {
            return ContextStorage::getApplication()->call($callback, $parameters, $defaultMethod);
        }
    }

    public function resolved($abstract)
    {
        if ($this->safeContainerInitializationMode) {
            return parent::resolved($abstract);
        } else {
            return ContextStorage::getApplication()->resolved($abstract);
        }
    }

    public function beforeResolving($abstract, Closure $callback = null)
    {
        if ($this->safeContainerInitializationMode) {
            parent::beforeResolving($abstract, $callback);
        } else {
            ContextStorage::getApplication()->beforeResolving($abstract, $callback);
        }
    }

    public function resolving($abstract, Closure $callback = null)
    {
        if ($this->safeContainerInitializationMode) {
            parent::resolving($abstract, $callback);
        } else {
            ContextStorage::getApplication()->resolving($abstract, $callback);
        }
    }

    public function afterResolving($abstract, Closure $callback = null)
    {
        if ($this->safeContainerInitializationMode) {
            parent::afterResolving($abstract, $callback);
        } else {
            ContextStorage::getApplication()->afterResolving($abstract, $callback);
        }
    }

    public function get(string $id)
    {
        if ($this->safeContainerInitializationMode) {
            return parent::get($id);
        } else {
            return ContextStorage::getApplication()->get($id);
        }
    }

    public function has(string $id): bool
    {
        if ($this->safeContainerInitializationMode) {
            return parent::has($id);
        } else {
            return ContextStorage::getApplication()->has($id);
        }
    }

    public function disableSafeContainerInitializationMode(): void
    {
        $this->safeContainerInitializationMode = false;
    }
}
