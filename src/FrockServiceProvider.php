<?php

namespace FrockDev\ToolsForLaravel;

use FrockDev\ToolsForLaravel\Console\AddGettersAndSettersToGrpcObjects;
use FrockDev\ToolsForLaravel\Console\AddToArrayToGrpcObjects;
use FrockDev\ToolsForLaravel\Console\CreateEndpointsFromProto;
use FrockDev\ToolsForLaravel\Console\AddNamespacesToComposerJson;
use FrockDev\ToolsForLaravel\Console\LoadNatsEndpoints;
use FrockDev\ToolsForLaravel\Console\NatsQueueConsumer;
use FrockDev\ToolsForLaravel\Console\PrepareProtoFiles;
use FrockDev\ToolsForLaravel\Console\RegisterEndpoints;
use FrockDev\ToolsForLaravel\Console\ResetNamespacesInComposerJson;
use FrockDev\ToolsForLaravel\ExceptionHandlers\ExceptionHandler;
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
        $this->commands(NatsQueueConsumer::class);
        $this->commands(RegisterEndpoints::class);
        $this->commands(AddToArrayToGrpcObjects::class);
    }

    public function boot()
    {

    }
}
