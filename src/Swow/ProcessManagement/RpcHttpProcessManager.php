<?php

namespace FrockDev\ToolsForLaravel\Swow\ProcessManagement;

use FrockDev\ToolsForLaravel\Annotations\DisableSpatieValidation;
use FrockDev\ToolsForLaravel\Annotations\Http;
use FrockDev\ToolsForLaravel\AnnotationsCollector\Collector;
use FrockDev\ToolsForLaravel\AnnotationsObjectModels\Annotation;
use FrockDev\ToolsForLaravel\Swow\Processes\RpcHttpProcess;
use FrockDev\ToolsForLaravel\Swow\Processes\ProcessesRegistry;
use FrockDev\ToolsForLaravel\Transport\AbstractMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class RpcHttpProcessManager
{
    private Collector $annotationCollector;

    public function __construct(Collector $annotationCollector)
    {
        $this->annotationCollector = $annotationCollector;
    }

    public function registerProcesses() {
        $routes = $this->findRoutes();
        $this->registerRoutesInLaravel($routes);
        $process = $this->createProcess($routes);
        $process->setName('rpc-http');
        ProcessesRegistry::register($process);
    }

    /**
     * @return array
     */
    private function findRoutes(): array {
        $result = [];
        $classes = $this->annotationCollector->getClassesByAnnotation(Http::class);
        foreach ($classes as $className=>$info) {

            if (array_key_exists(DisableSpatieValidation::class, $info['classAnnotations'])) {
                $disableSpatieValidation = true;
            } else {
                $disableSpatieValidation = false;
            }
            /**
             * @var string $annotationClassName
             * @var Annotation $annotation
             */
            foreach ($info['classAnnotations'] as $annotationClassName=>$annotation) {
                if ($annotationClassName==Http::class) {
                    /** @var Http $annotationExemplar */
                    $annotationExemplar = new $annotationClassName(...$annotation->getArguments());
                    $result[trim($annotationExemplar->path,'/')] = [
                        'method'=>strtoupper($annotationExemplar->method),
                        'endpoint'=>app()->make($className),
                        'disableSpatieValidation'=>$disableSpatieValidation,
                    ];
                }
            }
        }
        return $result;
    }

    private function createProcess(array $routes) {
        return new RpcHttpProcess($routes);
    }

    private function registerRoutesInLaravel(array $routes)
    {
        foreach ($routes as $route=>$info) {
            $disableSpatieValidation = $info['disableSpatieValidation'];
            $endpoint = $info['endpoint'];
            $function = function(Request $request) use ($endpoint, $disableSpatieValidation) {
                $endpoint->setContext($request->headers->all());
                $inputType = $endpoint::ENDPOINT_INPUT_TYPE;
                /** @var AbstractMessage $dto */
                if ($request->getMethod()=='GET') {
                    if ($disableSpatieValidation) {
                        $dto = $inputType::from($request->query->all());
                    } else {
                        $dto = $inputType::validateAndCreate($request->query->all());
                    }

                } elseif ($request->getMethod()=='POST') {
                    if ($disableSpatieValidation) {
                        $dto = $inputType::from($request->json()->all());
                    } else {
                        $dto = $inputType::validateAndCreate($request->json()->all());
                    }

                }
                /** @var AbstractMessage $result */
                $result = $endpoint->__invoke($dto);
                return $result;
            };
            if ($info['method']=='GET') {
                Route::get($route, $function);
            } elseif($info['method']=='POST') {
                Route::post($route, $function);
            } else {
                throw new \Exception('We use only GET and POST with RPC.');
            }
        }
    }
}
