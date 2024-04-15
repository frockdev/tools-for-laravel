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
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\DispatchesEvents;
use Laravel\Octane\Events\WorkerStarting;
use Monolog\Formatter\JsonFormatter;
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

        /** @var Application $app */
        $appBuilder = Application::configure(basePath: $baseDir);
        if (file_exists($baseDir.'/routes/api.php')) {
            $appBuilder->withRouting(
                web: $baseDir.'/routes/web.php',
                api: $baseDir.'/routes/api.php',
                commands: $baseDir.'/routes/console.php',
                health: '/up',
            );
        } else {
            $appBuilder->withRouting(
                web: $baseDir.'/routes/web.php',
                commands: $baseDir.'/routes/console.php',
                health: '/up',
            );
        }

        return $appBuilder->withExceptions(function (Exceptions $exceptions) {
                $commonErrorHandler = new CommonErrorHandler();

                // lets hack laravel, because we need to have our own logic
                // reflection will work only on startup, so it is ok
                $reflectionMethodUnauthenticated = new \ReflectionMethod($exceptions->handler, 'unauthenticated');
                $reflectionMethodUnauthenticated->setAccessible(true);

                $reflectionMethodConvertValidationExceptionToResponse = new \ReflectionMethod($exceptions->handler, 'convertValidationExceptionToResponse');
                $reflectionMethodConvertValidationExceptionToResponse->setAccessible(true);

                $reflectionMethodRenderExceptionResponse = new \ReflectionMethod($exceptions->handler, 'renderExceptionResponse');
                $reflectionMethodRenderExceptionResponse->setAccessible(true);

                $reflectionMethodReportThrowable = new \ReflectionMethod($exceptions->handler, 'reportThrowable');
                $reflectionMethodReportThrowable->setAccessible(true);

                $exceptions->render(function (\Throwable $e, Request $request) use
                (
                    $commonErrorHandler,
                    $exceptions,
                    $reflectionMethodUnauthenticated,
                    $reflectionMethodConvertValidationExceptionToResponse,
                    $reflectionMethodRenderExceptionResponse
                ) {
                    //here we will have our own logic

//                    if ($request->attributes->get('transport')==='http') {
//
//                    } else
                    if ($request->attributes->get('transport')==='rpc') {
                        $errorData = $commonErrorHandler->handleError($e);
                        return response()
                            ->json($errorData->errorData)
                            ->setStatusCode($errorData->errorCode)
                            ->header('x-trace-id', ContextStorage::get('x-trace-id'));
                    } elseif ($request->attributes->get('transport')==='nats') {
                        $errorData = $commonErrorHandler->handleError($e);
                        return response()
                            ->json($errorData->errorData)
                            ->setStatusCode($errorData->errorCode)
                            ->header('x-trace-id', ContextStorage::get('x-trace-id'));
                    }

                    // fallbacks:
                    if ($e instanceof HttpResponseException) {
                        return $e->getResponse();
                    }
                    if ($e instanceof AuthenticationException) {
                        return $reflectionMethodUnauthenticated->invoke($exceptions->handler, $request, $e);
                    }
                    if ($e instanceof ValidationException) {
                        return $reflectionMethodConvertValidationExceptionToResponse->invoke($exceptions->handler, $e, $request);
                    }
                    return $reflectionMethodRenderExceptionResponse->invoke($exceptions->handler, $request, $e);
                });
                $exceptions->report(function (\Throwable $e) use ($exceptions, $reflectionMethodReportThrowable) {
                    if (!app()->has('request')) {
                        return true;
                    } else {
                        if (request()->attributes->get('transport')==='rpc') {
                            return true;
                        } elseif (request()->attributes->get('transport')==='nats') {
                            return true;
                        } elseif (request()->attributes->get('transport')==='http') {
                            return true;
                        }
                        return true;
                    }
                });

            })
    ->withMiddleware(function (Middleware $middleware) {
            $middleware->validateCsrfTokens(
                except: ['api/*', 'rpc/*'],
            );

            $middleware->web(append: [

            ]);
        })->create();
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
