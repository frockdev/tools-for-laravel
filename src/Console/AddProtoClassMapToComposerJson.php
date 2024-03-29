<?php

namespace FrockDev\ToolsForLaravel\Console;

use Illuminate\Console\Command;

class AddProtoClassMapToComposerJson extends Command
{
    protected $signature = 'frock:add-proto-classmap-to-composer-json';

    public function handle() {

        $fileContent = file_get_contents(app_path().'/../composer.json');
        $fileContent = str_replace(' artisan ', ' frock.php ', $fileContent);
        $composerJson = json_decode($fileContent, true);
        @mkdir(app_path().'/../../protoPhp', 0777, true);
        $composerJson['autoload']['classmap'] = [
            "../protoPhp"
        ];

        file_put_contents(app_path().'/../composer.json', json_encode($composerJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

    }
}
