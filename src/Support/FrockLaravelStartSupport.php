<?php

namespace FrockDev\ToolsForLaravel\Support;

use FrockDev\ToolsForLaravel\ExceptionHandlers\CommonErrorHandler;
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
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
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

    private function bootstrapApplication(string $baseDir): Application {

        /** @var Application $app */
        $app = Application::configure(basePath: $baseDir)
            ->withRouting(
                web: $baseDir.'/routes/web.php',
                commands: $baseDir.'/routes/console.php',
                health: '/up',
            )
            ->withExceptions(function (Exceptions $exceptions) {
                $exceptions->report(function (\Throwable $e) use ($exceptions) {
                    if (!app()->has('request')) {
                        return true;
                    } else {
                        if (request()->attributes->get('transport')==='rpc') {
                            return true;
                        } elseif (request()->attributes->get('transport')==='nats') {
                            //report each one
                            $exceptions->handler->report($e);
                            return false;
                        } elseif (request()->attributes->get('transport')==='http') {
                            return true;
                        } else {
                            $exceptions->handler->report($e);
                            return false;
                        }
                    }
                });
                $commonErrorHandler = new CommonErrorHandler();
                $exceptions->render(function (\Throwable $e, Request $request) use ($exceptions, $commonErrorHandler) {
                    $errorData = $commonErrorHandler->handleError($e);
                    if ($request->attributes->get('transport')==='rpc') {
                        return response()
                            ->json($errorData->errorData)
                            ->setStatusCode($errorData->errorCode)
                            ->header('x-trace-id', ContextStorage::get('x-trace-id'));
                    } elseif ($request->attributes->get('transport')==='nats') {
                        return response()
                            ->json($errorData->errorData)
                            ->setStatusCode($errorData->errorCode)
                            ->header('x-trace-id', ContextStorage::get('x-trace-id'));
                    } elseif ($request->attributes->get('transport')==='http') {
                        return $exceptions->handler->render($request, $e);
                    } else {
                        return $exceptions->handler->render($request, $e);
                    }
                });
            })->create();
        return $app;
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
