<?php

use FrockDev\ToolsForLaravel\Support\AppModeResolver;
use FrockDev\ToolsForLaravel\Support\FrockLaravelStartSupport;
use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Swow\Channel;
use Symfony\Component\Console\Input\ArgvInput;

define('LARAVEL_START', microtime(true));

include dirname($GLOBALS['_composer_autoload_path']).'/psr/container/src/ContainerInterface.php';
include dirname($GLOBALS['_composer_autoload_path']).'/laravel/framework/src/Illuminate/Contracts/Container/Container.php';
include dirname($GLOBALS['_composer_autoload_path']).'/frock-dev/tools-for-laravel/src/LaravelHack/Illuminate/Container/Container.php';
include dirname($GLOBALS['_composer_autoload_path']).'/frock-dev/tools-for-laravel/src/LaravelHack/Basis/Nats/Connection.php';
file_put_contents(
    dirname($GLOBALS['_composer_autoload_path']).'/../artisan',
    '#!/usr/bin/env php' . PHP_EOL . '<?php' . PHP_EOL . 'require_once __DIR__ . \'/vendor/bin/frock.php\';' . PHP_EOL);
require_once $GLOBALS['_composer_autoload_path'];

$appModeResolver = new AppModeResolver();
$startSupport = new FrockLaravelStartSupport(
    $appModeResolver
);

$exitControlChannel = new Channel(1);
ContextStorage::setSystemChannel('exitChannel', $exitControlChannel);
ContextStorage::setCurrentRoutineName('main');
$laravelApp = $startSupport->initializeLaravel(realpath(dirname($GLOBALS['_composer_autoload_path']).'/../'));

$startSupport->loadServicesForArtisan();

$status = $laravelApp
    ->handleCommand(new ArgvInput);
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

sleep(2);
exit($status);
