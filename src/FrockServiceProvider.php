<?php

namespace FrockDev\ToolsForLaravel;

use FrockDev\ToolsForLaravel\AnnotationsCollector\Collector;
use FrockDev\ToolsForLaravel\Console\CollectAttributesToCache;
use FrockDev\ToolsForLaravel\Console\AddProtoClassMapToComposerJson;
use FrockDev\ToolsForLaravel\Console\GenerateEndpoints;
use FrockDev\ToolsForLaravel\Console\GenerateGrafanaMetrics;
use FrockDev\ToolsForLaravel\Console\GenerateHttpFiles;
use FrockDev\ToolsForLaravel\Swow\Liveness\Storage;
use FrockDev\ToolsForLaravel\Swow\Metrics\MetricFactory;
use FrockDev\ToolsForLaravel\Swow\Metrics\MetricFactoryInterface;
use Illuminate\Support\ServiceProvider;
use Jaeger\Config;
use OpenTracing\NoopTracer;
use OpenTracing\Tracer;
use Prometheus\Storage\InMemory;
use const Jaeger\SAMPLER_TYPE_CONST;

class FrockServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands(AddProtoClassMapToComposerJson::class);
        $this->commands(GenerateGrafanaMetrics::class);
        $this->commands(CollectAttributesToCache::class);
        $this->commands(GenerateEndpoints::class);
        $this->commands(GenerateHttpFiles::class);

        $this->app->singleton(\Prometheus\CollectorRegistry::class, function() {
            return new \Prometheus\CollectorRegistry(new InMemory());
        });

        $this->app->singleton(Storage::class, Storage::class);

        $this->app->bind(MetricFactoryInterface::class, MetricFactory::class);

        // own laravel attributes collector
        $collector = new Collector(app());
        $collector->collect(app_path());


        //@todo should make tracer async
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
    }

    public function boot()
    {

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
