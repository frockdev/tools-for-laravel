<?php

namespace FrockDev\ToolsForLaravel\Swow\ProcessManagement;

use FrockDev\ToolsForLaravel\Annotations\Http;
use FrockDev\ToolsForLaravel\AnnotationsCollector\Collector;
use FrockDev\ToolsForLaravel\AnnotationsObjectModels\Annotation;
use FrockDev\ToolsForLaravel\Swow\Processes\PhpRpcHttpProcess;
use FrockDev\ToolsForLaravel\Swow\Processes\ProcessesRegistry;

class PhpRpcHttpProcessManager
{
    private Collector $annotationCollector;

    public function __construct(Collector $annotationCollector)
    {
        $this->annotationCollector = $annotationCollector;
    }

    public function registerProcesses() {
        $routes = $this->findRoutes();
        $process = $this->createProcess($routes);
        $process->setName('prometheus-http');
        ProcessesRegistry::register($process);
    }

    /**
     * @return array
     */
    private function findRoutes(): array {
        $result = [];
        $classes = $this->annotationCollector->getClassesByAnnotation(Http::class);
        foreach ($classes as $className=>$info) {
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
                        'endpoint'=>app()->make($className)
                    ];
                }
            }
        }
        return $result;
    }

    private function createProcess(array $routes) {
        return new PhpRpcHttpProcess($routes);
    }
}
