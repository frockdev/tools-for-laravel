<?php

namespace FrockDev\ToolsForLaravel\Swow\ProcessManagement;
use FrockDev\ToolsForLaravel\Annotations\DisableSpatieValidation;
use FrockDev\ToolsForLaravel\Annotations\Nats;
use FrockDev\ToolsForLaravel\AnnotationsCollector\Collector;
use FrockDev\ToolsForLaravel\AnnotationsObjectModels\Annotation;
use FrockDev\ToolsForLaravel\FeatureFlags\EndpointFeatureFlagManager;
use FrockDev\ToolsForLaravel\Swow\Processes\AbstractProcess;
use FrockDev\ToolsForLaravel\Swow\Processes\NatsQueueConsumerProcess;
use FrockDev\ToolsForLaravel\Swow\Processes\ProcessesRegistry;
use Illuminate\Support\Facades\Log;

class NatsQueueProcessManager {

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
        Log::info('Registering Nats Queue processes');
        $classes = $this->collector->getClassesByAnnotation(Nats::class);

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
                if ($attributeClassName !== Nats::class) {
                    continue;
                }
                /** @var Nats $attributeExemplar */
                $attributeExemplar = new $attributeClassName(...$attributeInfo->getArguments());
                Log::info('Registering process: '.$attributeExemplar->name.'-'.$attributeExemplar->subject.'-'.$attributeExemplar->queueName);
                /** @var AbstractProcess $process */
                $process = $this->createProcess(
                    app()->make($endpointClassName),
                    $attributeExemplar->subject,
                    $attributeExemplar->queueName,
                    $disableSpatieValidation
                );
                $process->setName($attributeExemplar->name . '-' . $attributeExemplar->subject.'-'.$attributeExemplar->subject);
                ProcessesRegistry::register($process);
            }
        }
    }

    private function createProcess(object $consumer, string $subject, string $queueName, bool $disableSpatieValidation): AbstractProcess
    {
        Log::info('Constructing NatsQueueConsumerProcess for '.$subject.'-'.$queueName);
        return new NatsQueueConsumerProcess($consumer, $subject, $queueName, $disableSpatieValidation);
    }

}
