<?php

namespace FrockDev\ToolsForLaravel\Support;

use App\Console\Kernel;
use FrockDev\ToolsForLaravel\ExceptionHandlers\UniversalErrorHandler;
use FrockDev\ToolsForLaravel\Swow\Co\Co;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use FrockDev\ToolsForLaravel\Swow\Logging\CustomLogger;
use FrockDev\ToolsForLaravel\Swow\Logging\LogMessage;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\CustomProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\HttpProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\LivenessProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\NatsJetstreamProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\NatsQueueProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\RpcHttpProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\PrometheusHttpProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\SystemMetricsProcessManager;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Bootstrap\SetRequestForConsole;
use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;
use Prometheus\Storage\InMemory;
use ReflectionObject;
use Swow\Channel;

class FrockLaravelStartSupport
{
    private AppModeResolver $appModeResolver;

    public function __construct(AppModeResolver $appModeResolver)
    {
        $this->appModeResolver = $appModeResolver;
    }

    private function bootstrapApplication(): Application {


        $mainRegularApplication = new \Illuminate\Foundation\Application(
            dirname($GLOBALS['_composer_autoload_path']).'/../'
        );

        /*
        |--------------------------------------------------------------------------
        | Bind Important Interfaces
        |--------------------------------------------------------------------------
        |
        | Next, we need to bind some important interfaces into the container so
        | we will be able to resolve them when needed. The kernels serve the
        | incoming requests to this application from both the web and CLI.
        |
        */

        $mainRegularApplication->singleton(
            \Illuminate\Contracts\Http\Kernel::class,
            \App\Http\Kernel::class
        );

        $mainRegularApplication->singleton(
            \Illuminate\Contracts\Console\Kernel::class,
            Kernel::class
        );

        $mainRegularApplication->singleton(
            ExceptionHandler::class,
            UniversalErrorHandler::class
        );

        /*
        |--------------------------------------------------------------------------
        | Return The Application
        |--------------------------------------------------------------------------
        |
        | This script returns the application instance. The instance is given to
        | the calling script so we can separate the building of the instances
        | from the actual running of the application and sending responses.
        |
        */

        return $mainRegularApplication;

    }

    public function getInterStreamInstance() {
        return [
            \FrockDev\ToolsForLaravel\Swow\Liveness\Storage::class,
            InMemory::class,
        ];
    }

    public function initializeLaravel(bool $console = false): Application
    {
        $app = $this->bootstrapApplication();
        $method = (new ReflectionObject(
            $kernel = $app->make(HttpKernelContract::class)
        ))->getMethod('bootstrappers');

        $method->setAccessible(true);

        $bootstrappers = $this->injectBootstrapperBefore(
            RegisterProviders::class,
            SetRequestForConsole::class,
            $method->invoke($kernel)
        );

        $app->bootstrapWith($bootstrappers);

        $app->loadDeferredProviders();

        foreach ($this->getInterStreamInstance() as $className) {
            if (trim($className)=='') continue;
            $instance = $app->make($className);
            ContextStorage::setInterStreamInstance(get_class($instance), $instance);
        }

        if (config('frock.interStreamInstances')) {
            foreach (config('frock.interStreamInstances') as $key) {
                if (trim($key)=='') continue;
                $instance = $app->make($key);
                ContextStorage::setInterStreamInstance(get_class($instance), $instance);
            }
        }

        return $app;
    }

    /**
     * Inject a given bootstrapper before another bootstrapper.
     */
    protected function injectBootstrapperBefore(string $before, string $inject, array $bootstrappers): array
    {
        $injectIndex = array_search($before, $bootstrappers, true);

        if ($injectIndex !== false) {
            array_splice($bootstrappers, $injectIndex, 0, [$inject]);
        }

        return $bootstrappers;
    }

    public function loadServicesForArtisan() {
        $this->runLoggerService();
    }

    public function loadServices() {
        $this->runLoggerService();
        $this->runPrometheus();
        $this->runSystemMetricsCollector();
        if ($this->appModeResolver->isNatsAllowedToRun()) {
            $this->loadNatsService();
        }
        if ($this->appModeResolver->isHttpAllowedToRun()) {
            $this->runRpcHttpService();
            $this->runHttpService();
        }
        $this->runCustomProcesses();
        //latest
        $this->loadLivenessService();
    }

    private function runCustomProcesses() {
        /** @var CustomProcessManager $customProcessManager */
        $customProcessManager = app()->make(CustomProcessManager::class);

        if (!getenv('SKIP_INIT_PROCESSES')) {
            $customProcessManager->registerInitProcesses();
        }

        $customProcessManager->registerProcesses();
    }

    public function runLoggerService() {
        config(['logging.channels.custom'=> [
            'driver' => 'custom',
            'level'=>env('LOG_LEVEL', 'error'),
            'via' => CustomLogger::class,
        ]]);
        config(['logging.channels.stderr.formatter'=>env('LOG_STDERR_FORMATTER', JsonFormatter::class)]);
        config(['logging.default'=>'custom']);
        $channel = new Channel(1000);
        ContextStorage::setSystemChannel('log', $channel);
        Co::define('main-logger')
            ->charge(function($channel) {
            /** @var LogMessage $message */
            while ($message = $channel->pop()) {
                if ($message->severity===null) continue;
                Log
                    ::driver('stderr')->log(
                        $message->severity,
                        $message->message,
                        $message->context
                    );
            }
        })->args($channel)->runWithClonedDiContainer();
    }

    private function loadNatsService()
    {
        /** @var NatsJetstreamProcessManager $natsJetStreamManager */
        $natsJetStreamManager = app()->make(NatsJetstreamProcessManager::class);
        $natsJetStreamManager->registerProcesses();
        $natsQueueService = app()->make(NatsQueueProcessManager::class);
        $natsQueueService->registerProcesses();
    }

    private function loadLivenessService() {
        /** @var LivenessProcessManager $livenessProcessManager */
        $livenessProcessManager = app()->make(LivenessProcessManager::class);
        $livenessProcessManager->registerProcesses();
    }

    private function runPrometheus()
    {
        /** @var PrometheusHttpProcessManager $prometheusManager */
        $prometheusManager = app()->make(PrometheusHttpProcessManager::class);
        $prometheusManager->registerProcesses();
    }

    private function runSystemMetricsCollector()
    {
        /** @var SystemMetricsProcessManager $systemMetricsManager */
        $systemMetricsManager = app()->make(SystemMetricsProcessManager::class);
        $systemMetricsManager->registerProcesses();
    }

    private function runRpcHttpService()
    {
        /** @var RpcHttpProcessManager $manager */
        $manager = app()->make(RpcHttpProcessManager::class);
        $manager->registerProcesses();
    }
    private function runHttpService()
    {
        /** @var HttpProcessManager $manager */
        $manager = app()->make(HttpProcessManager::class);
        $manager->registerProcesses();
    }

}
