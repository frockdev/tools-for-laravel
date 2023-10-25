<?php

namespace FrockDev\ToolsForLaravel;

use FrockDev\ToolsForLaravel\Console\AddToArrayToGrpcObjects;
use FrockDev\ToolsForLaravel\Console\CreateEndpointsFromProto;
use FrockDev\ToolsForLaravel\Console\AddNamespacesToComposerJson;
use FrockDev\ToolsForLaravel\Console\LoadNatsEndpoints;
use FrockDev\ToolsForLaravel\Console\NatsQueueConsumer;
use FrockDev\ToolsForLaravel\Console\PrepareProtoFiles;
use FrockDev\ToolsForLaravel\Console\RegisterEndpoints;
use FrockDev\ToolsForLaravel\Console\ResetNamespacesInComposerJson;
use FrockDev\ToolsForLaravel\ExceptionHandlers\ExceptionHandler;
use FrockDev\ToolsForLaravel\Nats\ConnectionOptions;
use FrockDev\ToolsForLaravel\Nats\EncodedConnection;
use FrockDev\ToolsForLaravel\Nats\Encoders\GRPCEncoder;
use FrockDev\ToolsForLaravel\Nats\Encoders\JSONEncoder;
use FrockDev\ToolsForLaravel\Nats\Messengers\GrpcNatsMessenger;
use FrockDev\ToolsForLaravel\Nats\Messengers\JsonNatsMessenger;
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

        $this->app->singleton(JsonNatsMessenger::class, function ($app) {
            $options = new ConnectionOptions([
                'host'=>config('nats.address'),
            ]);
            $connection = new EncodedConnection($options, new JsonEncoder());
            $connection->setDebug(true);
            return new JsonNatsMessenger($connection);
        });

        $this->app->singleton(GrpcNatsMessenger::class, function ($app) {
            $options = new ConnectionOptions([
                'host'=>config('nats.address'),
            ]);
            $connection = new EncodedConnection($options, new GRPCEncoder());
            $connection->setDebug(true);
            return new GrpcNatsMessenger($connection);
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/nats.php' => config_path('nats.php'),
        ]);
    }
}
