<?php

namespace FrockDev\ToolsForLaravel\Console;

use Illuminate\Console\Command;

class ResetNamespacesInComposerJson extends Command
{
    protected $signature = 'frock:reset-namespaces-in-composer-json';

    public function handle() {
        $composerJson = json_decode(file_get_contents(app_path().'/../composer.json'), true);

        $composerJson['autoload']['psr-4'] = [];
        $composerJson['autoload']['psr-4']['App\\'] = 'app/';
        $composerJson['autoload']['psr-4']['Database\\Factories\\'] = 'database/factories/';
        $composerJson['autoload']['psr-4']['Database\\Seeders\\'] = 'database/seeders/';

        file_put_contents(app_path().'/../composer.json', json_encode($composerJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    }
}
