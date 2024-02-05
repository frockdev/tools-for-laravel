<?php

namespace FrockDev\ToolsForLaravel\Support;

use FrockDev\ToolsForLaravel\AnnotationsCollector\Collector;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\HttpProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\LivenessProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\NatsJetstreamProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\NatsQueueProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\RpcHttpProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\PrometheusHttpProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\SystemMetricsProcessManager;

class FrockLaravelStartSupport
{
    private AppModeResolver $appModeResolver;
    private Collector $collector;

    public function __construct(AppModeResolver $appModeResolver)
    {
        $this->appModeResolver = $appModeResolver;
        $this->collector = app()->make(Collector::class);
    }

    public function initializeLaravel(string $basePath): \Illuminate\Foundation\Application {
        /** @var \Illuminate\Foundation\Application $app */
        $app = require_once $basePath.'/bootstrap/app.php';
        $app->bootstrapWith([
            \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
            \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
            \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
            \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
//            \Illuminate\Foundation\Bootstrap\SetRequestForConsole::class,
            \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
            \Illuminate\Foundation\Bootstrap\BootProviders::class,
        ]);
        return $app;
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
//            $this->runHttpService();
        }
        //latest
        $this->loadLivenessService();
    }

    public function runLoggerService() {

        config(['logging.channels.custom'=> [
            'driver' => 'custom',
            'level'=>env('LOG_LEVEL', 'error'),
            'via' => \FrockDev\ToolsForLaravel\Swow\Logging\CustomLogger::class,
        ]]);
        config(['logging.channels.stderr.formatter'=>env('LOG_STDERR_FORMATTER',\Monolog\Formatter\JsonFormatter::class)]);
        config(['logging.default'=>'custom']);

        \Swow\Coroutine::run(function() {
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
        });
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
