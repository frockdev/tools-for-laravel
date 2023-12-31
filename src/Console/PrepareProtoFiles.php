<?php

namespace FrockDev\ToolsForLaravel\Console;

use Illuminate\Console\Command;

class PrepareProtoFiles extends Command
{

    protected $signature = 'frock:prepare-proto-files {name}';

    public function handle() {
        $composerJson = json_decode(file_get_contents(app_path().'/../composer.json'), true);
        $projectOwner = $this->getProjectOwnerByFullName($composerJson['name']);
        $projectName = $this->argument('name');
        shell_exec('rm -rf '.app_path().'/../../protoPrepared');
        shell_exec('cp -r '.app_path().'/../../proto '. app_path().'/../../protoPrepared');

        // $dirPath contain path to directory whose files are to be listed
        $files = glob(app_path() . "/../../protoPrepared/*/*/*.proto");
        foreach ($files as $file) {
            if (is_file($file)) {
                $newContent = $this->modifyProtoFile(file_get_contents($file), $projectName, $projectOwner);
                file_put_contents($file, $newContent);
            }
        }
    }

    private function getProjectOwnerByFullName(string $projectFullName): string
    {
        $nameExploded = explode('/', $projectFullName);
        return $nameExploded[0];
    }

    private function modifyProtoFile(string $fileContent, string $projectName,  string $projectOwner): string
    {
        preg_match('/package (.*);/', $fileContent, $matches);
        if (!isset($matches[1])) {
            throw new \Exception('Package name not found in proto file. Don\'t forget write in file "package <package_name>;"'.$fileContent);
        }
        $packageName = $matches[1];

//        // this is new
//        preg_match('%\s*//\s*messagesPrefix\s*=\s*(.*);%', $fileContent, $matches);
//        $internalPrefix = $matches[1] ?? '';
//        $internalPrefix = trim($internalPrefix, '\'"');
//        if ($internalPrefix!=='') {
//            preg_match_all('/rpc\s+(\w*)\s*\((.*)\)\s*returns\s*\((.*)\);/', $fileContent, $matches);
//            $internalRequests = $matches[2];
//            $internalResponses = $matches[3];
//
//            preg_match_all('/message\s+(\w*)\s*{/', $fileContent, $matches);
//
//            $strings = $matches[0];
//            $messages = $matches[1];
//
//            foreach ($strings as $key=>$stringToChange) {
//                if (in_array($messages[$key], $internalRequests) || in_array($messages[$key], $internalResponses)) {
//                    echo 'dont touch external message '.$messages[$key]."\n";
//                    continue;
//                }
//                $fileContent = str_replace($stringToChange, 'message '.$internalPrefix.ucfirst($messages[$key]).' {', $fileContent);
//                $fileContent = preg_replace('/^\s+('.$messages[$key].')\s+(\w*\s*=\s*\d+;)$/m', '    '.$internalPrefix.ucfirst($messages[$key]).' $2', $fileContent);
//                // and also for repeated
//                $fileContent = preg_replace('/^\s+repeated\s+('.$messages[$key].')\s+(\w*\s*=\s*\d+;)$/m', '    repeated '.$internalPrefix.ucfirst($messages[$key]).' $2', $fileContent);
//            }
//        }
//        //end of new

        $packageNameExploded = explode('.', $packageName);
        $version = ucfirst($packageNameExploded[count($packageNameExploded)-1]);
        unset($packageNameExploded[count($packageNameExploded)-1]);

        foreach ($packageNameExploded as &$packageNameExplodedPart) {
            $packageNameExplodedPart = ucfirst($packageNameExplodedPart);
        }
//        //option go_package="/frock_example_posts_v1";
////option php_namespace = "Posts\\v1"; // physical service (FrockExample) + Module Name + Version
////option php_metadata_namespace = "Posts\\v1\\Meta";

        $fileContent .= "\noption go_package=\"/".strtolower(implode('_', $packageNameExploded))."_".strtolower($version)."\";";
        $fileContent .= "\noption php_namespace = \""
            .$this->fixNameFromAnyToCamelCase($projectOwner)
            .'\\\\'.$this->fixNameFromAnyToCamelCase($projectName).'Contracts'
            .'\\\\'.implode('\\\\', $packageNameExploded)."\\\\".$version."\";";
        $fileContent .= "\noption php_metadata_namespace = \""
            .$this->fixNameFromAnyToCamelCase($projectOwner)
            .'\\\\'.$this->fixNameFromAnyToCamelCase($projectName).'Contracts'
            .'\\\\'.implode('\\\\', $packageNameExploded)."\\\\".$version."\\\\Meta\";";
        return $fileContent;

    }

    private function fixNameFromAnyToCamelCase($projectName): string
    {
        $projectName = preg_replace("/[^\w0-9]+/", '-', $projectName);
        $exploded = explode('-', $projectName);
        foreach ($exploded as &$word) {
            $word = ucfirst($word);
        }
        return implode('', $exploded);
    }
}
