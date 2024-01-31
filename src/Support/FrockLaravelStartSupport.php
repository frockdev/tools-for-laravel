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
            'via' => \FrockDev\ToolsForLaravel\Swow\Logging\CustomLogger::class,
        ]]);
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

//    public function getHttpDefaultTemplate() {
//        return [
//            'name' => 'http',
//            'type' => Server::SERVER_HTTP,
//            'host' => '0.0.0.0',
//            'port' => 8080,
//            'sock_type' => SWOOLE_SOCK_TCP,
//            'callbacks' => [
//                Event::ON_REQUEST => function ($request, $response) {
//                    $response->end('<h1>Hello Hyperf!</h1>');
//                },
//            ],
//            'options' => [
//                // Whether to enable request lifecycle event
//                'enable_request_lifecycle' => false,
//            ],
//        ];
//    }
//
//    public function getDefaultServersConfig() {
//        if (env('APP_ENV')=='local') {
//            $cpuNum = 1;
//        } elseif (env('SWOOLE_WORKER_NUM', 0)!==0) {
//            $cpuNum = (int)env('SWOOLE_WORKER_NUM');
//        } else {
//            $cpuNum = swoole_cpu_num();
//        }
//        return [
//            'mode' => SWOOLE_PROCESS,
//            'servers' => [],
//            'type'=>BaseHyperfServer::class,
//            'settings' => [
//                Constant::OPTION_ENABLE_COROUTINE => true,
//                Constant::OPTION_WORKER_NUM => $cpuNum,
//                Constant::OPTION_PID_FILE => BASE_PATH . '/storage/hyperf.pid',
//                Constant::OPTION_OPEN_TCP_NODELAY => true,
//                Constant::OPTION_MAX_COROUTINE => 100000,
//                Constant::OPTION_OPEN_HTTP2_PROTOCOL => true,
//                Constant::OPTION_MAX_REQUEST => 100000,
//                Constant::OPTION_SOCKET_BUFFER_SIZE => 2 * 1024 * 1024,
//                Constant::OPTION_BUFFER_OUTPUT_SIZE => 2 * 1024 * 1024,
//            ],
//            'callbacks' => [
//                Event::ON_WORKER_START => [\Hyperf\Framework\Bootstrap\WorkerStartCallback::class, 'onWorkerStart'],
//                Event::ON_PIPE_MESSAGE => [\Hyperf\Framework\Bootstrap\PipeMessageCallback::class, 'onPipeMessage'],
//                Event::ON_WORKER_EXIT => [\Hyperf\Framework\Bootstrap\WorkerExitCallback::class, 'onWorkerExit'],
//            ],
//        ];
//    }
//
//    public array $httpTemplate = [
//        'name' => 'http',
//        'type' => Server::SERVER_HTTP,
//        'host' => '0.0.0.0',
//        'port' => 8080,
//        'sock_type' => SWOOLE_SOCK_TCP,
//        'callbacks' => [
//            Event::ON_REQUEST => [HttpProtobufServer::class, 'onRequest'],
//        ],
//        'options' => [
//            // Whether to enable request lifecycle event
//            'enable_request_lifecycle' => false,
//        ],
//    ];
//
//    public function enableHttpIfNeeded(array $serverConfig): array {
//        $finalServerConfig = $serverConfig;
//        /** @var Collector $collector */
//        $collector = app()->make(Collector::class);
//        $needHttp =
//            count($collector->getClassesByAnnotation(Http::class))>0
//            && (
//                $this->appModeResolver->isHttpAllowedToRun()
//            );
//
//        //////http
//        $foundConfigIndex = false;
//        foreach ($finalServerConfig['servers'] as $index => $server) {
//            if ($server['name'] == 'http') {
//                $foundConfigIndex = $index;
//            }
//        }
//        if ($needHttp) {
//            $httpConfig = $this->httpTemplate;
//            if ($foundConfigIndex!==false) {
//                foreach ($finalServerConfig['servers'][$foundConfigIndex] as $index=> $value) {
//                    if ($index=='callbacks') continue;
//                    $httpConfig[$index] = $value;
//                }
//                $finalServerConfig['servers'][$foundConfigIndex] = $httpConfig;
//            } else {
//                $finalServerConfig['servers'][] = $httpConfig;
//            }
////            $config->set('exceptions.handler.http', [HttpExceptionHandler::class]);
//        } else {
//            if ($foundConfigIndex!==false) {
//                unset($finalServerConfig['servers'][$foundConfigIndex]);
//            }
//        }
//        return $finalServerConfig;
//        ////end of http
//    }
//
//
//
//    public function configureMetric(\Hyperf\Nano\App $app) {
//        $namespace = Str::snake(Str::camel(env('APP_NAME', 'skeleton')));
//        $app->config(['metric'=>
//            [
//                'default' => env('METRIC_DRIVER', 'prometheus'),
//                'use_standalone_process' => env('TELEMETRY_USE_STANDALONE_PROCESS', true),
//                'enable_default_metric' => env('TELEMETRY_ENABLE_DEFAULT_TELEMETRY', true),
//                'default_metric_interval' => env('DEFAULT_METRIC_INTERVAL', 5),
//                'metric' => [
//                    'prometheus' => [
//                        'driver' => \Hyperf\Metric\Adapter\Prometheus\MetricFactory::class,
//                        'mode' => Constants::SCRAPE_MODE,
//                        'namespace' => $namespace,
//                        'scrape_host' => env('PROMETHEUS_SCRAPE_HOST', '0.0.0.0'),
//                        'scrape_port' => env('PROMETHEUS_SCRAPE_PORT', '9502'),
//                        'scrape_path' => env('PROMETHEUS_SCRAPE_PATH', '/metrics'),
//                        'push_host' => env('PROMETHEUS_PUSH_HOST', '0.0.0.0'),
//                        'push_port' => env('PROMETHEUS_PUSH_PORT', '9091'),
//                        'push_interval' => env('PROMETHEUS_PUSH_INTERVAL', 5),
//                        'redis_config'=>env('PROMETHEUS_REDIS_CONFIG', 'metrics'),
//                    ],
//                    'noop' => [
//                        'driver' => \Hyperf\Metric\Adapter\NoOp\MetricFactory::class,
//                    ],
//                ],
//            ]],
//            \Hyperf\Nano\Constant::CONFIG_REPLACE
//        );
//        config(['metric.metric.prometheus.namespace'=>$namespace]);
//
//        $app->config(['metric.metric.prometheus.redis_config'=>'metrics']);
//        $metricsRedisConnection = [
//            'host' => env('REDIS_METRICS_HOST', 'redis-metrics-'.config('app.name')),
//            'auth' => env('REDIS_METRICS_AUTH', null),
//            'port' => (int) env('REDIS_PORT', 6379),
//            'db' => (int) env('REDIS_DB', 0),
////            'persistent_connections' => true,
//            'pool' => [
//                'min_connections' => 1,
//                'max_connections' => 10,
//                'connect_timeout' => 10.0,
//                'wait_timeout' => 3.0,
//                'heartbeat' => -1,
//                'max_idle_time' => (float) env('REDIS_MAX_IDLE_TIME', 60),
//            ],
//        ];
//        $app->config(['redis.metrics'=>
//            $metricsRedisConnection
//        ], \Hyperf\Nano\Constant::CONFIG_REPLACE
//        );
//        $app->config(['app_name'=>config('app.name')]);
//
//    }
//
//    public function configureLogger(ConfigInterface|Repository $config) {
//        $config->set('logging.stderr.formatter', env('LOG_STDERR_FORMATTER',\Monolog\Formatter\JsonFormatter::class));
//    }
//
//    public function configureNats(\Hyperf\Nano\App $app) {
//        /** @var Collector $collector */
//        $collector = app()->make(Collector::class);
//        $needNats = ((count($collector->getClassesByAnnotation(Nats::class))>0)
//            || (count($collector->getClassesByAnnotation(NatsJetstream::class))>0))
//            && (
//                $this->appModeResolver->isNatsAllowedToRun()
//            );
//        if ($needNats) {
//            $natsConfig = $this->getNatsTemplate();
//            $app->config(['natsJetstream' => [
//                    'jetstream' =>
//                        $natsConfig
//                    ]
//                ], \Hyperf\Nano\Constant::CONFIG_REPLACE
//            );
//        }
//    }
//
//    public function getNatsTemplate() {
//        return [
//            'options' => [
//                'host' => env('NATS_HOST', 'nats.nats'),
//                'port' => env('NATS_PORT', 4222),
//                'user' => env('NATS_USER'),
//                'pass' => env('NATS_PASS'),
//                'timeout'=>(float) env('NATS_TIMEOUT', 1),
//            ],
//            'pool' => [
//                'min_connections' => env('NATS_MIN_CONNECTIONS', 1),
//                'max_connections' => env('NATS_MAX_CONNECTIONS', 10),
//                'connect_timeout' => env('NATS_CONNECT_TIMEOUT', 10.0),
//                'wait_timeout' => env('NATS_WAIT_TIMEOUT', 3.0),
//                'heartbeat' => env('NATS_HEARTBEAT', -1),
//                'max_idle_time' => env('NATS_MAX_IDLE_TIME', 60),
//            ],
//
//        ];
//    }
//
//    public array $grpcTemplate = [
//        'name' => 'grpc',
//        'type' => Server::SERVER_HTTP,
//        'host' => '0.0.0.0',
//        'port' => 9090,
//        'sock_type' => SWOOLE_SOCK_TCP,
//        'callbacks' => [
//            Event::ON_REQUEST => [\FrockDev\ToolsForLaravel\Servers\GrpcProtobufServer::class, 'onRequest'],
//        ],
//    ];
//
//    public function enableGrpcIfNeeded(array $serverConfig): array
//    {
//        /** @var Collector $collector */
//        $collector = app()->make(Collector::class);
//        $needGrpc = count($collector->getClassesByAnnotation(Grpc::class))>0
//            && (
//                $this->appModeResolver->isGrpcAllowedToRun()
//            );
//
//        $finalServerConfig = $serverConfig;
//        ////grpc
//        $foundConfigIndex = false;
//        /**
//         * @var string $index
//         * @var array $server
//         */
//        foreach ($finalServerConfig['servers'] as $index => $server) {
//            if ($server['name'] == 'grpc') {
//                $foundConfigIndex = $index;
//            }
//        }
//
//        if ($needGrpc) {
//            $grpcConfig = $this->grpcTemplate;
//            if ($foundConfigIndex!==false) {
//                foreach ($finalServerConfig['servers'][$foundConfigIndex] as $index => $value) {
//                    if ($index=='callbacks') continue;
//                    $grpcConfig[$index] = $value;
//                }
//                $finalServerConfig['servers'][$foundConfigIndex] = $grpcConfig;
//            } else {
//                $finalServerConfig['servers'][] = $grpcConfig;
//            }
//
//        } else {
//            if ($foundConfigIndex!==false) {
//                unset($finalServerConfig['servers'][$foundConfigIndex]);
//            }
//        }
//        ////end of grpc
//        return $finalServerConfig;
//    }
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
