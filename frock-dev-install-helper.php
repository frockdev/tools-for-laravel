<?php

$composerJson = json_decode(file_get_contents(getcwd().'/php/composer.json'), true);

if (getenv('REVERSE')=='0') {
    if (!isset($composerJson['repositories']['frock_laravel']))
        $composerJson['repositories']['frock_laravel'] = [
            'type' => 'path',
            'url' => 'frock-laravel',
        ];

    $composerJson['require']['frock-dev/frock-laravel'] = 'dev-main';
} elseif (getenv('REVERSE')=='1') {
    if (isset($composerJson['repositories']['frock_laravel'])) {
        unset($composerJson['repositories']['frock_laravel']);
    }

    unset($composerJson['require']['frock-dev/frock-laravel']);
} else {
    echo 'Please set REVERSE=1 or REVERSE=0';
    exit(1);
}

$resultJson = json_encode($composerJson, JSON_PRETTY_PRINT);
$resultJson = str_replace('\\/', '/', $resultJson);

file_put_contents(getcwd().'/php/composer.json', $resultJson);
