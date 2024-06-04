<?php

namespace FrockDev\ToolsForLaravel\Support;

use FrockDev\ToolsForLaravel\AnnotationsCollector\Collector;
use FrockDev\ToolsForLaravel\Swow\Co\Co;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use FrockDev\ToolsForLaravel\Swow\Logging\CustomLogger;
use FrockDev\ToolsForLaravel\Swow\Logging\LogMessage;
use FrockDev\ToolsForLaravel\Swow\Logging\ValuesMaskJsonFormatter;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\CustomProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\HttpProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\LivenessProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\NatsJetstreamProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\NatsQueueProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\RpcHttpProcessManager;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\DispatchesEvents;
use Laravel\Octane\Events\WorkerStarting;
use Prometheus\Storage\InMemory;
use Swow\Channel;

class FrockLaravelStartSupport
{
    use DispatchesEvents;
    private AppModeResolver $appModeResolver;

    public function __construct(AppModeResolver $appModeResolver)
    {
        $this->appModeResolver = $appModeResolver;
    }

    private function bootstrapApplication(string $baseDir): Application {
        return include $baseDir.'/bootstrap/app.php';
    }

    public function getInterStreamInstance() {
        return [
            \FrockDev\ToolsForLaravel\Swow\Liveness\Storage::class,
            InMemory::class,
        ];
    }

    public function initializeLaravel(string $basePath): Application
    {
        $app = $this->bootstrapApplication($basePath);
        $appFactory = new ApplicationFactory($basePath);

        $appFactory->bootstrap($app);

        Collector::getInstance()->collect(app_path());

        $appFactory->warm($app, $app->make('config')->get('octane.warm', []));
        $appFactory->warm($app, [
            \FrockDev\ToolsForLaravel\Swow\Liveness\Storage::class,
            InMemory::class,
        ]);

        $this->dispatchEvent($app, new WorkerStarting($app));

        $app->singleton(ApplicationFactory::class);
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
        $this->registerLoggerProcess();
    }

    public function registerProcesses() {
        $this->registerLoggerProcess();
        if ($this->appModeResolver->isNatsAllowedToRun()) {
            Log::info('Nats is allowed to run');
            $this->registerNatsProcesses();
        }
        if ($this->appModeResolver->isHttpAllowedToRun()) {
            $this->registerHttpRpcProcess();
            $this->registerBasicHttpProcess();
        }
        $this->registerCustomProcesses();
        //latest
        $this->registerLivenessProcess();
    }

    private function registerCustomProcesses() {
        /** @var CustomProcessManager $customProcessManager */
        $customProcessManager = app()->make(CustomProcessManager::class);

        if (!getenv('SKIP_INIT_PROCESSES')) {
            $customProcessManager->registerInitProcesses();
        }

        $customProcessManager->registerProcesses();
    }

    public function registerLoggerProcess() {
        config(['logging.channels.custom'=> [
            'driver' => 'custom',
            'level'=>env('LOG_LEVEL', 'error'),
            'via' => CustomLogger::class,
        ]]);
        config(['logging.channels.stderr.formatter'=>env('LOG_STDERR_FORMATTER', ValuesMaskJsonFormatter::class)]);
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

    private function registerNatsProcesses()
    {
        Log::info('Starting Nats');
        /** @var NatsJetstreamProcessManager $natsJetStreamManager */
        $natsJetStreamManager = app()->make(NatsJetstreamProcessManager::class);
        $natsJetStreamManager->registerProcesses();
        $natsQueueService = app()->make(NatsQueueProcessManager::class);
        $natsQueueService->registerProcesses();
    }

    private function registerLivenessProcess() {
        /** @var LivenessProcessManager $livenessProcessManager */
        $livenessProcessManager = app()->make(LivenessProcessManager::class);
        $livenessProcessManager->registerProcesses();
    }

    private function registerHttpRpcProcess()
    {
        /** @var RpcHttpProcessManager $manager */
        $manager = app()->make(RpcHttpProcessManager::class);
        $manager->registerProcesses();
    }
    private function registerBasicHttpProcess()
    {
        /** @var HttpProcessManager $manager */
        $manager = app()->make(HttpProcessManager::class);
        $manager->registerProcesses();
    }

}
