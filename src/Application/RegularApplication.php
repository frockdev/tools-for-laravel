<?php

namespace FrockDev\ToolsForLaravel\Application;

class RegularApplication extends \Illuminate\Foundation\Application
{
    public function flushProviders() {
        $this->serviceProviders = [];
        $this->loadedProviders = [];
    }

    public function getAllProviders() {
        return $this->serviceProviders;
    }

    public function forgetInstancesExceptThese(array $instances) {
        foreach ($this->instances as $key => $instance) {
            if (!in_array($key, $instances)) {
                unset($this->instances[$key]);
            }
        }
    }
}
