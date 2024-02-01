<?php

namespace FrockDev\ToolsForLaravel\Support;

use FrockDev\ToolsForLaravel\Annotations\Grpc;
use FrockDev\ToolsForLaravel\Annotations\Http;
use FrockDev\ToolsForLaravel\Annotations\Nats;
use FrockDev\ToolsForLaravel\Annotations\NatsJetstream;
use FrockDev\ToolsForLaravel\AnnotationsCollector\Collector;
use FrockDev\ToolsForLaravel\BaseServer\BaseHyperfServer;
use FrockDev\ToolsForLaravel\Servers\HttpProtobufServer;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\NatsJetstreamProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\NatsQueueProcessManager;
use FrockDev\ToolsForLaravel\Swow\ProcessManagement\PrometheusHttpProcessManager;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Metric\Adapter\Prometheus\Constants;
use Hyperf\Server\Event;
use Hyperf\Server\Server;
use Illuminate\Config\Repository;
use Illuminate\Support\Str;
use Swoole\Constant;
use function Hyperf\Support\env;

class FrockLaravelStartSupport
{

    private AppModeResolver $appModeResolver;

    public function __construct(AppModeResolver $appModeResolver)
    {
        $this->appModeResolver = $appModeResolver;
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
        if ($this->appModeResolver->isNatsAllowedToRun()) {
            $this->loadNatsService();
        }
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

    private function runPrometheus()
    {
        /** @var PrometheusHttpProcessManager $prometheusManager */
        $prometheusManager = app()->make(PrometheusHttpProcessManager::class);
        $prometheusManager->registerProcesses();
    }

}
