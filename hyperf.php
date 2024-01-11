<?php
// This file is auto-generated. Do not edit it manually.
use FrockDev\ToolsForLaravel\EventLIsteners\RunNatsListener;
use FrockDev\ToolsForLaravel\HyperfProxies\StdOutLoggerProxy;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Nano\Factory\AppFactory;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;
use Swoole\Constant;
use function Hyperf\Support\env;

require_once __DIR__ . '/vendor/autoload.php';
$appModeResolver = new \FrockDev\ToolsForLaravel\Support\AppModeResolver();
$startSupport = new \FrockDev\ToolsForLaravel\Support\HyperfLaravelStartSupport(
    $appModeResolver
);
$laravelApp = $startSupport->initializeLaravel(__DIR__);

config(['logging.channels.stderr.processors' => [
    PsrLogMessageProcessor::class,
    \FrockDev\ToolsForLaravel\Logging\CoroutineTolerantProcessor::class,
]]);
config(['logging.channels.stderr.formatter' => \Monolog\Formatter\JsonFormatter::class]);

$hyperfApp = AppFactory::create(dependencies: [
    LoggerInterface::class => StdOutLoggerProxy::class,
    StdoutLoggerInterface::class=> StdOutLoggerProxy::class,
]);
app()->singleton(\Hyperf\Nano\App::class, \Hyperf\Nano\App::class);
app()->instance(\Hyperf\Nano\App::class, $hyperfApp);

$startSupport->configureNats($hyperfApp);
$serverConfig = $startSupport->getDefaultServersConfig();
$serverConfig = $startSupport->enableHttpIfNeeded($serverConfig);
$serverConfig = $startSupport->enableGrpcIfNeeded($serverConfig);

if (count($serverConfig['servers']) == 0) {
    $serverConfig['servers'][] = $startSupport->getHttpDefaultTemplate();
}

$hyperfApp->config([
    'server' => $serverConfig,
], \Hyperf\Nano\Constant::CONFIG_REPLACE
); // for hyperf
config([
    'server' => $serverConfig,
]); //for laravel

// for hyperf
$hyperfApp->config(['logging.stderr.formatter' => env('LOG_STDERR_FORMATTER', \Monolog\Formatter\JsonFormatter::class)]);

$startSupport->configureMetric($hyperfApp);

$hyperfApp->addListener(\Hyperf\Framework\Event\BootApplication::class, function ($event) {
    app()->make(RunNatsListener::class)->handle();
});

$hyperfApp->run();

