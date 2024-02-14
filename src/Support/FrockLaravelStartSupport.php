<?php

namespace FrockDev\ToolsForLaravel\Support;

use FrockDev\ToolsForLaravel\Application\Application;
use FrockDev\ToolsForLaravel\ExceptionHandlers\UniversalErrorHandler;
use FrockDev\ToolsForLaravel\Swow\Co\Co;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\CustomProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\HttpProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\LivenessProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\NatsJetstreamProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\NatsQueueProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\RpcHttpProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\PrometheusHttpProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\SystemMetricsProcessManager;
use Illuminate\Container\Container;

class FrockLaravelStartSupport
{
    private AppModeResolver $appModeResolver;

    public function __construct(AppModeResolver $appModeResolver)
    {
        $this->appModeResolver = $appModeResolver;
    }

    private function bootstrapApplication(): \Illuminate\Foundation\Application {

        $safeApp = new Application(
            '/var/www/php',
            true
        );
        $safeApp->disableSafeContainerInitializationMode();


        $unsafeApp = new \Illuminate\Foundation\Application(
            '/var/www/php'
        );


        \Illuminate\Foundation\Application::setInstance($safeApp);

        Container::setInstance($safeApp);


        ContextStorage::setCurrentRoutineName('main');
        ContextStorage::setApplication($unsafeApp);

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

        $unsafeApp->singleton(
            \Illuminate\Contracts\Http\Kernel::class,
            \App\Http\Kernel::class
        );

        $unsafeApp->singleton(
            \Illuminate\Contracts\Console\Kernel::class,
            \App\Console\Kernel::class
        );

        $unsafeApp->singleton(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
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

        return $unsafeApp;

    }

    public function initializeLaravel(bool $console = false): \Illuminate\Foundation\Application
    {
        $app = $this->bootstrapApplication();
        if ($console===true) {
            $app->bootstrapWith([
                \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
                \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
                \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
                \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
                \Illuminate\Foundation\Bootstrap\SetRequestForConsole::class,
                \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
                \Illuminate\Foundation\Bootstrap\BootProviders::class,
            ]);
        } else {
            $app->bootstrapWith([
                \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
                \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
                \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
                \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
                \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
                \Illuminate\Foundation\Bootstrap\BootProviders::class,
            ]);
        }
        return $app;
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
        $customProcessManager->registerProcesses();
    }

    public function runLoggerService() {

        config(['logging.channels.custom'=> [
            'driver' => 'custom',
            'level'=>env('LOG_LEVEL', 'error'),
            'via' => \FrockDev\ToolsForLaravel\Swow\Logging\CustomLogger::class,
        ]]);
        config(['logging.channels.stderr.formatter'=>env('LOG_STDERR_FORMATTER',\Monolog\Formatter\JsonFormatter::class)]);
        config(['logging.default'=>'custom']);

        Co::define('main-logger')
            ->charge(function() {
            $channel = new \Swow\Channel(1000);
            ContextStorage::setSystemChannel('log', $channel);

            /** @var \FrockDev\ToolsForLaravel\Swow\Logging\LogMessage $message */
            while ($message = $channel->pop()) {
                if ($message->severity===null) continue;
                \Illuminate\Support\Facades\Log
                    ::driver('stderr')->log(
                        $message->severity,
                        $message->message,
                        $message->context
                    );
            }
        })->run();
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
