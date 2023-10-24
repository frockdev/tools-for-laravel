<?php

namespace FrockDev\ToolsForLaravel;

use FrockDev\ToolsForLaravel\Nats\Connection;
use FrockDev\ToolsForLaravel\Nats\ConnectionOptions;
use FrockDev\ToolsForLaravel\Nats\EncodedConnection;
use FrockDev\ToolsForLaravel\Nats\Encoders\GRPCEncoder;
use FrockDev\ToolsForLaravel\Nats\Encoders\JSONEncoder;
use FrockDev\ToolsForLaravel\Nats\Messengers\GrpcNatsMessenger;
use FrockDev\ToolsForLaravel\Nats\Messengers\JsonNatsMessenger;
use Illuminate\Support\ServiceProvider;

class RRJobsServiceProvider extends ServiceProvider
{
    public function register()
    {
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
}
