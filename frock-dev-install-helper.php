<?php

$composerJson = json_decode(file_get_contents(getcwd().'/composer.json'), true);

if (getenv('REVERSE')=='0') {
    if (!isset($composerJson['repositories']['frock_laravel']))
        $composerJson['repositories']['frock_laravel'] = [
            'type' => 'path',
            'url' => 'frock-laravel',
            "options" => [
                "symlink" => true
            ]
        ];

    $composerJson['require']['frock-dev/tools-for-laravel'] = 'dev-main';
} elseif (getenv('REVERSE')=='1') {
    if (isset($composerJson['repositories']['frock_laravel'])) {
        unset($composerJson['repositories']['frock_laravel']);
    }

    unset($composerJson['require']['frock-dev/tools-for-laravel']);
} else {
    echo 'Please set REVERSE=1 or REVERSE=0';
    exit(1);
}

$resultJson = json_encode($composerJson, JSON_PRETTY_PRINT);
$resultJson = str_replace('\\/', '/', $resultJson);

file_put_contents(getcwd().'/composer.json', $resultJson);
