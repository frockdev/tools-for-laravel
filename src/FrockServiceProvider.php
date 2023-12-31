<?php

namespace FrockDev\ToolsForLaravel;

use Basis\Nats\Configuration;
use FrockDev\ToolsForLaravel\Console\AddToArrayToGrpcObjects;
use FrockDev\ToolsForLaravel\Console\CreateEndpointsFromProto;
use FrockDev\ToolsForLaravel\Console\AddNamespacesToComposerJson;
use FrockDev\ToolsForLaravel\Console\GenerateGrafanaMetrics;
use FrockDev\ToolsForLaravel\Console\GenerateTestsForPublicMethodsOnModules;
use FrockDev\ToolsForLaravel\Console\HttpConsumer;
use FrockDev\ToolsForLaravel\Console\LoadHttpEndpoints;
use FrockDev\ToolsForLaravel\Console\LoadNatsEndpoints;
use FrockDev\ToolsForLaravel\Console\NatsQueueConsumer;
use FrockDev\ToolsForLaravel\Console\PrepareProtoFiles;
use FrockDev\ToolsForLaravel\Console\RegisterEndpoints;
use FrockDev\ToolsForLaravel\Console\ResetNamespacesInComposerJson;
use FrockDev\ToolsForLaravel\EventLIsteners\BeforeEndpointCalledListener;
use FrockDev\ToolsForLaravel\EventLIsteners\RequestGotListener;
use FrockDev\ToolsForLaravel\EventLIsteners\WorkerListenStartedHandler;
use FrockDev\ToolsForLaravel\Events\BeforeEndpointCalled;
use FrockDev\ToolsForLaravel\Events\RequestGot;
use FrockDev\ToolsForLaravel\Events\WorkerListenStarted;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Dummy\DummyMetrics;
use FrockDev\ToolsForLaravel\MetricsAbstractions\Dummy\DummyRPC;
use FrockDev\ToolsForLaravel\NatsCustomization\CustomNatsClient;
use FrockDev\ToolsForLaravel\NatsMessengers\JsonNatsMessenger;
use FrockDev\ToolsForLaravel\NatsMessengers\GrpcNatsMessenger;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Jaeger\Config;
use OpenTracing\NoopTracer;
use OpenTracing\Tracer;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Metrics\Metrics;
use const Jaeger\SAMPLER_TYPE_CONST;

class FrockServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands(CreateEndpointsFromProto::class);
        $this->commands(AddNamespacesToComposerJson::class);
        $this->commands(ResetNamespacesInComposerJson::class);
        $this->commands(PrepareProtoFiles::class);
        $this->commands(LoadNatsEndpoints::class);
        $this->commands(LoadHttpEndpoints::class);
        $this->commands(NatsQueueConsumer::class);
        $this->commands(HttpConsumer::class);
        $this->commands(RegisterEndpoints::class);
        $this->commands(AddToArrayToGrpcObjects::class);
        $this->commands(GenerateTestsForPublicMethodsOnModules::class);
        $this->commands(GenerateGrafanaMetrics::class);


        $this->app->bind(GrpcNatsMessenger::class, function ($app) {
            $options = new Configuration([
                'host'=>config('nats.address'),
                'user'=>config('nats.user'),
                'pass'=>config('nats.pass'),
                'timeout'=>config('nats.timeout', 30),
            ]);
            $client = new CustomNatsClient($options, Log::getLogger());
            return new GrpcNatsMessenger($client);
        });

        $this->app->bind(JsonNatsMessenger::class, function ($app) {
            $options = new Configuration([
                'host'=>config('nats.address'),
                'user'=>config('nats.user'),
                'pass'=>config('nats.pass'),
                'timeout'=>config('nats.timeout', 30),
            ]);
            $client = new CustomNatsClient($options, Log::getLogger());
            return new JsonNatsMessenger($client);
        });

        $this->app->singleton(Tracer::class, function($app) {
            if (config('jaeger.traceEnabled', false)===true) {
                $config = new Config(
                    [
                        'sampler' => [
                            'type' => config('jaeger.samplerType', SAMPLER_TYPE_CONST),
                            'param' => true,
                        ],
                        'logging' => true,
                        'dispatch_mode' => Config::JAEGER_OVER_BINARY_UDP,
                        "local_agent" => [
                            "reporting_host" => config('jaeger.reportingHost', "jaeger-all-in-one.jaeger"),
                            "reporting_port" => config('jaeger.reportingPort', 6832),
                        ],
                    ],
                    config('app.name'),
                );
                return $config->initializeTracer();
            } else {
                return new NoopTracer();
            }
        });

        $this->app->singleton(Metrics::class, function($app) {
            if (config('frock.disableMetrics')===true) {
                return new DummyMetrics(new DummyRPC());
            } else {
                return new Metrics(RPC::create('tcp://127.0.0.1:6001'));
            }

        });

    }

    public function boot()
    {

        Event::listen(BeforeEndpointCalled::class,
            [BeforeEndpointCalledListener::class, 'handle']
        );

        Event::listen(WorkerListenStarted::class,
            [WorkerListenStartedHandler::class, 'handle']
        );

        Event::listen(RequestGot::class,
            [RequestGotListener::class, 'handle']
        );

        $this->publishes([
            __DIR__.'/../config/frock.php' => config_path('frock.php'),
        ]);

        $this->publishes([
            __DIR__.'/../config/nats.php' => config_path('nats.php'),
        ]);

        $this->publishes([
            __DIR__.'/../config/jaeger.php' => config_path('jaeger.php'),
        ]);
    }
}
