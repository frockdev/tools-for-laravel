<?php

namespace FrockDev\ToolsForLaravel\Swow\ProcessManagement;

use FrockDev\ToolsForLaravel\Annotations\InitProcess;
use FrockDev\ToolsForLaravel\Annotations\Process;
use FrockDev\ToolsForLaravel\AnnotationsCollector\Collector;
use FrockDev\ToolsForLaravel\AnnotationsObjectModels\Annotation;
use FrockDev\ToolsForLaravel\Swow\Processes\AbstractProcess;
use FrockDev\ToolsForLaravel\Swow\Processes\ProcessesRegistry;

class CustomProcessManager
{
    private Collector $collector;

    public function __construct(Collector $colelctor)
    {
        $this->collector = $colelctor;
    }

    public function registerInitProcesses() {
        $registeredProcessesByNames = [];
        $annotatedProcesses = $this->collector->getClassesByAnnotation(InitProcess::class);
        foreach ($annotatedProcesses as $className=>$annotationInfo) {
            /**
             * @var string $annotationClassName
             * @var Annotation $annotation
             */
            foreach ($annotationInfo['classAnnotations'] as $annotationClassName=>$annotation) {
                if ($annotationClassName!==InitProcess::class) continue;
                $annotationInstance = new $annotationClassName(...$annotation->getArguments());
                $process = app()->make($className);
                if (!$process instanceof AbstractProcess) {
                    throw new \Exception('Process must exends AbstractProcess');
                }
                if (array_key_exists($annotationInstance->name, $registeredProcessesByNames)) {
                    throw new \Exception('Process with name '.$annotationInstance->name.' already registered');
                }
                $process->setName($annotationInstance->name);
                $registeredProcessesByNames[$annotationInstance->name] = true;
                ProcessesRegistry::registerInit($process);
            }
        }
    }

    public function registerProcesses() {
        $registeredProcessesByNames = [];
        $annotatedProcesses = $this->collector->getClassesByAnnotation(Process::class);
        foreach ($annotatedProcesses as $className=>$annotationInfo) {
            /**
             * @var string $annotationClassName
             * @var Annotation $annotation
             */
            foreach ($annotationInfo['classAnnotations'] as $annotationClassName=>$annotation) {
                if ($annotationClassName!==Process::class) continue;
                $annotationInstance = new $annotationClassName(...$annotation->getArguments());
                $process = app()->make($className);
                if (!$process instanceof AbstractProcess) {
                    throw new \Exception('Process must implement ProcessInterface');
                }
                if (array_key_exists($annotationInstance->name, $registeredProcessesByNames)) {
                    throw new \Exception('Process with name '.$annotationInstance->name.' already registered');
                }
                $process->setName($annotationInstance->name);
                $registeredProcessesByNames[$annotationInstance->name] = true;
                ProcessesRegistry::register($process);
            }
        }
    }
}
