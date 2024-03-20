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
    $restartablePath[] = $baseDir.'/../phpProto/';
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
    $serverProcess->start($outputWriter);

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
                $serverProcess->signal(SIGKILL);
                $serverProcess = new \Symfony\Component\Process\Process($processCommand);
                $serverProcess->start($outputWriter);
                return;
            } elseif ($reloadablePathFound) {
                $serverProcess->signal(SIGKILL);
                $serverProcess = new \Symfony\Component\Process\Process($processCommand);
                $serverProcess->setEnv(['SKIP_INIT_PROCESSES' => 'true']);
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
