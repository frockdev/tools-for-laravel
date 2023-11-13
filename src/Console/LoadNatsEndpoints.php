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
        $natsEndpointsConfigFile = '<?php '.
            "//This file is autogenerated by Frock.Dev"."\n".
            "\n".' return [ '."\n";
        foreach (scandir(app_path().'/Modules') as $module) {
            if ($module === '.' || $module === '..' || !is_dir(app_path().'/Modules/'.$module)) {
                continue;
            }

            foreach (scandir(app_path().'/Modules/'.$module.'/Endpoints') as $subService) {
                if ($subService === '.' || $subService === '..' || !is_dir(app_path() . '/Modules/' . $module . '/Endpoints/' . $subService)) {
                    continue;
                }

            foreach (scandir(app_path() . '/Modules/' . $module . '/Endpoints/' . $subService) as $version) {
                if ($version === '.' || $version === '..' || !is_dir(app_path() . '/Modules/' . $module . '/Endpoints/' . $subService . '/' . $version)) {
                    continue;
                }

                foreach (scandir(app_path() . '/Modules/' . $module . '/Endpoints/' . $subService . '/' . $version) as $endpoint) {
                    if ($endpoint === '.' || $endpoint === '..' || !is_file(app_path() . '/Modules/' . $module . '/Endpoints/' . $subService . '/' . $version . '/' . $endpoint)) {
                        continue;
                    }

                    $endpointClass = 'App\\Modules\\' . $module . '\\Endpoints\\' .$subService .'\\'. $version . '\\' . substr($endpoint, 0, -4);

                    $this->info('Loading ' . $endpointClass);

                    $reflectionClass = new \ReflectionClass($endpointClass);
//                    if (str_contains($reflectionClass->getDocComment(), 'deprecated')) {
//                        $this->info('Deprecated '.$endpointClass. ' Skipping...');
//                        continue;
//                    }

                    foreach ($reflectionClass->getMethods() as $method) {
                        if ($method->name !== 'run') continue;
//                        if ($method->isDeprecated()) {
//                            $this->info('Deprecated '.$endpointClass.'::run Skipping...');
//                            continue;
//                        }
                        $attributes = $method->getAttributes(\FrockDev\ToolsForLaravel\Annotations\Nats::class);

                        if (count($attributes) === 0) {
                            continue;
                        }

                        /** @var Nats $attribute */
                        $attribute = $attributes[0]->newInstance();

                        $arguments = $method->getParameters();
                        $inputType = $arguments[0]->getType();

                        $outputType = $method->getReturnType();

                        $this->info('Loading ' . $attribute->subject);
                        $envVarName = $this->convertSubjectToEnvVarName($attribute->subject);
                        $natsEndpointsConfigFile .= "\tenv('" . $envVarName . "_NATS_CHANNEL', '" . $attribute->subject . "') => [\n";
                        if ($attribute->stream!==null) {
                            $natsEndpointsConfigFile .= "\t\t'stream' => '" . $attribute->stream . "',\n";
                        }
                        if ($attribute->consumerName!==null) {
                            $natsEndpointsConfigFile .= "\t\t'consumerName' => '" . $attribute->consumerName . "',\n";
                        }
                        $natsEndpointsConfigFile .= "\t\t'subject' => env('" . $envVarName . "_NATS_CHANNEL', '" . $attribute->subject . "'),\n";
                        $natsEndpointsConfigFile .= "\t\t'endpoint' => '" . $endpointClass . "',\n";
                        $natsEndpointsConfigFile .= "\t\t'inputType' => '" . $inputType . "',\n";
                        $natsEndpointsConfigFile .= "\t\t'outputType' => '" . $outputType . "',\n";
                        $natsEndpointsConfigFile .= "\t" . '],' . "\n";
                    }
                }
            }
            }
        }

        $natsEndpointsConfigFile .= '];';
        file_put_contents(config_path().'/natsEndpoints.php', $natsEndpointsConfigFile);
    }

    /**
     * This function make all letters capitalized, and all non-letters converts to underscores
     * @param string $subject
     * @return void
     */
    private function convertSubjectToEnvVarName(string $subject): string
    {
        $envVarName = '';
        foreach (str_split($subject) as $char) {
            if (ctype_alpha($char)) {
                $envVarName .= strtoupper($char);
            } else {
                $envVarName .= '_';
            }
        }
        return $envVarName;

    }
}
