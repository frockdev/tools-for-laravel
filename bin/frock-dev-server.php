<?php

use FrockDev\ToolsForLaravel\Swow\ContextStorage;
use Spatie\Watcher\Watch;
use Swow\Channel;
use Symfony\Component\Process\Process;

$autoloaderPath = $GLOBALS['_composer_autoload_path'];

require_once $autoloaderPath;

Swow\Coroutine::run(function() use ($autoloaderPath) {

    $outputWriter = function ($type, $buffer): void {
        echo $buffer;
    };

    $baseDir = realpath(dirname($autoloaderPath).'/../');

    //restartable - it is with launching init processes
    //reloadable - it is without launching inti processes

    $restartablePath = [];
    $restartablePath[] = $baseDir.'/public/';
    $restartablePath[] = $baseDir.'/../protoPhp/';
    $restartablePath[] = $baseDir.'/bootstrap/';
    $restartablePath[] = $baseDir.'/database/';
    $restartablePath[] = $baseDir.'/../packages/frock/';
    $restartablePath[] = $baseDir.'/vendor/frock-dev/tools-for-laravel/';
    $restartablePath[] = $baseDir.'/config/';
    $restartablePath[] = $baseDir.'/vendor/';
    $restartablePath[] = $baseDir.'/.env';
    $restartablePath[] = $baseDir.'/composer.json';
    $restartablePath[] = $baseDir.'/composer.json';

    $reloadablePath = [];
    $reloadablePath[] = $baseDir.'/app/';
    $reloadablePath[] = $baseDir.'/resources/';
    $reloadablePath[] = $baseDir.'/routes/';
    $processCommand = ['php', $baseDir.'/vendor/bin/frock-server.php'];
    $serverProcess = new \Symfony\Component\Process\Process($processCommand);
    $serverProcess->setEnv(['FROCK_DEV_SERVER' => 'true']);
    $serverProcess->start($outputWriter);
    echo 'Starting frock-dev-server' . PHP_EOL;

    Watch::paths(...$restartablePath, ...$reloadablePath)
        ->onAnyChange(function (string $type, string $path) use (&$serverProcess, $restartablePath, $reloadablePath, $processCommand, $baseDir, $outputWriter) {
            $restartablePathFound = null;
            $reloadablePathFound = null;
            echo 'Changed: ' . $path . PHP_EOL;
            foreach ($restartablePath as $onePath) {
                if (str_starts_with($path,$onePath)) {
                    $restartablePathFound = $onePath;
                    echo 'Changed Restartable: ' . $path . PHP_EOL;
                    break;
                }
            }
            if (!$restartablePathFound) {
                foreach ($reloadablePath as $onePath) {
                    if (str_starts_with($path,$onePath)) {
                        $reloadablePathFound = $onePath;
                        echo 'Changed Reloadable: ' . $path . PHP_EOL;
                        break;
                    }
                }
            }

            if ($restartablePathFound) {
                echo 'Status: '.$serverProcess->getStatus()."\n";
                while (!$serverProcess->isTerminated()) {
                    $serverProcess->signal(SIGTERM);
                    echo 'Status: '.$serverProcess->getStatus()."\n";
                    usleep(100000);
                }
                echo 'Terminated. Restarting'."\n";
                sleep(1);
                $serverProcess = new \Symfony\Component\Process\Process($processCommand);
                $serverProcess->setEnv(['FROCK_DEV_SERVER' => 'true']);
                $serverProcess->start($outputWriter);
                return;
            } elseif ($reloadablePathFound) {
                echo 'Status: '.$serverProcess->getStatus()."\n";
                while (!$serverProcess->isTerminated()) {
                    $serverProcess->signal(SIGTERM);
                    echo 'Status: '.$serverProcess->getStatus()."\n";
                    usleep(100000);
                }
                echo 'Terminated. Restarting'."\n";
                sleep(1);
                $serverProcess = new \Symfony\Component\Process\Process($processCommand);
                $serverProcess->setEnv(['SKIP_INIT_PROCESSES' => 'true']);
                $serverProcess->setEnv(['FROCK_DEV_SERVER' => 'true']);
                $serverProcess->start($outputWriter);
                return;
            }
            echo 'But not needed to restart'."\n";

        })
        ->start();
});


$exitControlChannel = new Channel(1);
ContextStorage::setSystemChannel('exitChannel', $exitControlChannel);

\Swow\Coroutine::run(static function () use ($exitControlChannel): void {
    \Swow\Signal::wait(\Swow\Signal::INT);
    $exitControlChannel->push(\Swow\Signal::TERM);
});
\Swow\Coroutine::run(static function () use ($exitControlChannel): void {
    \Swow\Signal::wait(\Swow\Signal::TERM);
    $exitControlChannel->push(\Swow\Signal::TERM);
});

$exitCode = $exitControlChannel->pop();

sleep(1);
echo 'Exited: ' . $exitCode . PHP_EOL;
exit($exitCode);
