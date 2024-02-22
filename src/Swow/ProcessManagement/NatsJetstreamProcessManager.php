<?php

namespace FrockDev\ToolsForLaravel\Swow\ProcessManagement;
use FrockDev\ToolsForLaravel\Annotations\DisableSpatieValidation;
use FrockDev\ToolsForLaravel\Annotations\NatsJetstream;
use FrockDev\ToolsForLaravel\AnnotationsCollector\Collector;
use FrockDev\ToolsForLaravel\AnnotationsObjectModels\Annotation;
use FrockDev\ToolsForLaravel\FeatureFlags\EndpointFeatureFlagManager;
use FrockDev\ToolsForLaravel\Swow\Processes\AbstractProcess;
use FrockDev\ToolsForLaravel\Swow\Processes\NatsJetStreamConsumerProcess;
use FrockDev\ToolsForLaravel\Swow\Processes\ProcessesRegistry;

class NatsJetstreamProcessManager {

    private Collector $collector;
    private EndpointFeatureFlagManager $endpointFeatureFlagManager;

    public function __construct(Collector                  $collector,
                                EndpointFeatureFlagManager $endpointFeatureFlagManager
    )
    {
        $this->collector = $collector;
        $this->endpointFeatureFlagManager = $endpointFeatureFlagManager;
    }

    public function registerProcesses() {
        $classes = $this->collector->getClassesByAnnotation(NatsJetstream::class);

        foreach ($classes as $endpointClassName => $classAttributesInfo) {
            if (!$this->endpointFeatureFlagManager->checkIfEndpointEnabled($endpointClassName)) {
                continue;
            }
            if (array_key_exists(DisableSpatieValidation::class, $classAttributesInfo['classAnnotations'])) {
                $disableSpatieValidation = true;
            } else {
                $disableSpatieValidation = false;
            }
            /**
             * @var string $attributeClassName
             * @var Annotation $attributeInfo
             */
            foreach ($classAttributesInfo['classAnnotations'] as $attributeClassName => $attributeInfo) {
                if ($attributeClassName !== NatsJetstream::class) {
                    continue;
                }
                /** @var NatsJetstream $attributeExemplar */
                $attributeExemplar = new $attributeClassName(...$attributeInfo->getArguments());
                /** @var AbstractProcess $process */
                $process = $this->createProcess(
                    app()->make($endpointClassName),
                    $attributeExemplar->subject,
                    $attributeExemplar->streamName,
                    $attributeExemplar->periodInMicroseconds,
                    $disableSpatieValidation
                );
                $process->setName($attributeExemplar->name . '-' . $attributeExemplar->subject.'-'.$attributeExemplar->subject);
                ProcessesRegistry::register($process);
            }
        }
    }

    private function createProcess(object $consumer, string $subject, string $stream, ?int $periodInMicroseconds=null, bool $disableSpatieValidation=false): AbstractProcess
    {
        return new NatsJetStreamConsumerProcess($consumer, $subject, $stream, $periodInMicroseconds, $disableSpatieValidation);
    }

}
