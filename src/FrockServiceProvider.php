<?php

namespace FrockDev\ToolsForLaravel;

use FrockDev\ToolsForLaravel\Console\CreateEndpointsFromProto;
use FrockDev\ToolsForLaravel\Console\AddNamespacesToComposerJson;
use FrockDev\ToolsForLaravel\Console\LoadNatsEndpoints;
use FrockDev\ToolsForLaravel\Console\PrepareProtoFiles;
use FrockDev\ToolsForLaravel\Console\ResetNamespacesInComposerJson;
use Illuminate\Support\ServiceProvider;

class FrockServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands(CreateEndpointsFromProto::class);
        $this->commands(AddNamespacesToComposerJson::class);
        $this->commands(ResetNamespacesInComposerJson::class);
        $this->commands(PrepareProtoFiles::class);
        $this->commands(LoadNatsEndpoints::class);
    }

    public function boot()
    {

    }
}
