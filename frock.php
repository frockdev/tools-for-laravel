<?php

use FrockDev\ToolsForLaravel\Support\AppModeResolver;
use FrockDev\ToolsForLaravel\Support\FrockLaravelStartSupport;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use FrockDev\ToolsForLaravel\Swow\Processes\ProcessesRegistry;
use Swow\Channel;

require_once __DIR__ . '/vendor/autoload.php';

$appModeResolver = new AppModeResolver();
$startSupport = new FrockLaravelStartSupport(
    $appModeResolver
);

$exitControlChannel = new Channel(1);
ContextStorage::setSystemChannel('exitChannel', $exitControlChannel);

$laravelApp = $startSupport->initializeLaravel(__DIR__);

$startSupport->loadServices(); //load services depends on mode

ProcessesRegistry::runRegisteredProcesses();

\Swow\Coroutine::run(static function () use ($exitControlChannel): void {
    \Swow\Signal::wait(\Swow\Signal::INT);
    $exitControlChannel->push(\Swow\Signal::TERM);
});
\Swow\Coroutine::run(static function () use ($exitControlChannel): void {
    \Swow\Signal::wait(\Swow\Signal::TERM);
    $exitControlChannel->push(\Swow\Signal::TERM);
});

$exitCode = $exitControlChannel->pop();

echo 'Exited: ' . $exitCode . PHP_EOL;
exit($exitCode);
