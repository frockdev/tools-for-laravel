<?php

use FrockDev\ToolsForLaravel\Application\Application;
use FrockDev\ToolsForLaravel\Support\AppModeResolver;
use FrockDev\ToolsForLaravel\Support\FrockLaravelStartSupport;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Swow\Channel;

define('LARAVEL_START', microtime(true));

require_once $GLOBALS['_composer_autoload_path'];

$appModeResolver = new AppModeResolver();
$startSupport = new FrockLaravelStartSupport(
    $appModeResolver
);

$exitControlChannel = new Channel(1);
ContextStorage::setSystemChannel('exitChannel', $exitControlChannel);
ContextStorage::setCurrentRoutineName('main');
$laravelApp = $startSupport->initializeLaravel(console: true);

$startSupport->loadServicesForArtisan();

$kernel = $laravelApp->make(Illuminate\Contracts\Console\Kernel::class);

$status = $kernel->handle(
    $input = new Symfony\Component\Console\Input\ArgvInput,
    new Symfony\Component\Console\Output\ConsoleOutput
);

/*
|--------------------------------------------------------------------------
| Shutdown The Application
|--------------------------------------------------------------------------
|
| Once Artisan has finished running, we will fire off the shutdown events
| so that any final work may be done by the application before we shut
| down the process. This is the last thing to happen to the request.
|
*/

$kernel->terminate($input, $status);
sleep(2);
exit($status);
