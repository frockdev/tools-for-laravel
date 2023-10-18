<?php

namespace FrockDev\ToolsForLaravel\Console;

use Illuminate\Console\Command;

class AddNamespacesToComposerJson extends Command
{
    protected $signature = 'frock:add-generated-namespaces-to-composer-json {DEVSPACE_NAME}';

    public function handle() {

        $composerJson = json_decode(file_get_contents(app_path().'/../composer.json'), true);

        $composerJson['autoload']['psr-4'] = [];
        $composerJson['autoload']['psr-4']['App\\'] = 'app/';
        $composerJson['autoload']['psr-4']['Database\\Factories\\'] = 'database/factories/';
        $composerJson['autoload']['psr-4']['Database\\Seeders\\'] = 'database/seeders/';

        $projectName = $this->argument('DEVSPACE_NAME');
        $projectName = $this->fixProjectName($projectName);

        $projectOwner = $this->getProjectOwnerByFullName($composerJson['name']);

        shell_exec('mv '.app_path().'/../protoGenerated/'.ucfirst($projectOwner).'/'.$projectName.'Contracts/* '.app_path().'/../protoGenerated');
        shell_exec('rm -rf '.app_path().'/../protoGenerated/'.ucfirst($projectOwner));

        foreach (scandir('/var/www/php/protoGenerated/') as $moduleDir) {
            if ($moduleDir==='.' || $moduleDir==='..') continue;
            $composerJson['autoload']['psr-4'][ucfirst($projectOwner).'\\'.$projectName.'Contracts'.'\\'.$moduleDir.'\\'] = 'protoGenerated/'.$moduleDir.'/';
        }

        file_put_contents(app_path().'/../composer.json', json_encode($composerJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

    }

    private function fixProjectName($projectName): string
    {
        $projectName = preg_replace("/[^\w0-9]+/", '-', $projectName);
        $exploded = explode('-', $projectName);
        foreach ($exploded as &$word) {
            $word = ucfirst($word);
        }
        return implode('', $exploded);
    }

    private function getProjectOwnerByFullName(string $projectFullName): string
    {
        $nameExploded = explode('/', $projectFullName);
        return $nameExploded[0];
    }
}
