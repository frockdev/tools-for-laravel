<?php

namespace FrockDev\ToolsForLaravel\Console;

use FrockDev\ToolsForLaravel\Annotations\Nats;
use Illuminate\Console\Command;

class LoadNatsEndpoints extends Command
{
    protected $signature = 'frock:load-nats-endpoints';

    protected $description = 'Load NATS endpoints';

    public function handle()
    {
        $configArray = [];
        foreach (scandir(app_path().'/Modules') as $module) {
            if ($module === '.' || $module === '..' || !is_dir(app_path().'/Modules/'.$module)) {
                continue;
            }

            foreach (scandir(app_path().'/Modules/'.$module.'/Endpoints') as $version) {
                if ($version === '.' || $version === '..' || !is_dir(app_path().'/Modules/'.$module.'/Endpoints/'.$version)) {
                    continue;
                }

                foreach (scandir(app_path().'/Modules/'.$module.'/Endpoints/'.$version) as $endpoint) {
                    if ($endpoint === '.' || $endpoint === '..' || !is_file(app_path().'/Modules/'.$module.'/Endpoints/'.$version.'/'.$endpoint)) {
                        continue;
                    }

                    $endpointClass = 'App\\Modules\\'.$module.'\\Endpoints\\'.$version.'\\'.substr($endpoint, 0, -4);

                    $this->info('Loading '.$endpointClass);

                    $reflectionClass = new \ReflectionClass($endpointClass);

                    foreach ($reflectionClass->getMethods() as $method) {
                        $attributes = $method->getAttributes(\FrockDev\ToolsForLaravel\Annotations\Nats::class);

                        if (count($attributes) === 0) {
                            continue;
                        }

                        /** @var Nats $attribute */
                        $attribute = $attributes[0]->newInstance();

                        $this->info('Loading '.$attribute->subject);

                        $configArray['endpoints'][$attribute->subject] = [
                            'endpoint' => $endpointClass,
                            'type' => $attribute->type,
                        ];
                    }
                }
            }
        }
        file_put_contents(config_path().'/natsEndpoints.php', '<?php return '.var_export($configArray, true).';');
    }
}
