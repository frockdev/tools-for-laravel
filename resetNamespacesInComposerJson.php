<?php
$composerJson = json_decode(file_get_contents('/var/www/php/composer.json'), true);

$composerJson['autoload']['psr-4'] = [];
$composerJson['autoload']['psr-4']['App\\'] = 'app/';
$composerJson['autoload']['psr-4']['Database\\Factories\\'] = 'database/factories/';
$composerJson['autoload']['psr-4']['Database\\Seeders\\'] = 'database/seeders/';

file_put_contents('/var/www/php/composer.json', json_encode($composerJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

$appConfig = file_get_contents('/var/www/php/config/app.php');
if (str_contains($appConfig, "App\Providers\AppServiceProvider::class,\n\t\tApp\Providers\EndpointsServiceProvider::class,")) {
    $appConfig = str_replace("App\Providers\AppServiceProvider::class,\n\t\tApp\Providers\EndpointsServiceProvider::class,", 'App\Providers\AppServiceProvider::class,', $appConfig);
    file_put_contents('/var/www/php/config/app.php', $appConfig);
}
