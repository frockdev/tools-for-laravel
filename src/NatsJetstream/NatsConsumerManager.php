<?php

namespace FrockDev\ToolsForLaravel\NatsJetstream;

use FrockDev\ToolsForLaravel\Annotations\Nats;
use FrockDev\ToolsForLaravel\Annotations\NatsJetstream;
use FrockDev\ToolsForLaravel\AnnotationsCollector\Collector;
use FrockDev\ToolsForLaravel\AnnotationsObjectModels\Annotation;
use FrockDev\ToolsForLaravel\FeatureFlags\EndpointFeatureFlagManager;
use FrockDev\ToolsForLaravel\NatsJetstream\Processes\NatsConsumerProcess;
use Hyperf\Process\AbstractProcess;
use Hyperf\Process\ProcessManager;
use Psr\Container\ContainerInterface;

class NatsConsumerManager
{
    public function __construct(private ContainerInterface $container, private EndpointFeatureFlagManager $endpointFeatureFlagManager)
    {
    }

    public function run()
    {
        $collector = app()->get(Collector::class);

        $classes = $collector->getClassesByAnnotation(Nats::class);

        foreach ($classes as $className => $classAttributesInfo) {
            if (!$this->endpointFeatureFlagManager->checkIfEndpointEnabled($collector, $className)) {
                continue;
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
                $nums = $attributeExemplar->nums;
                $process = $this->createProcess(
                    app()->make($className),
                    $attributeExemplar->subject,
                    $attributeExemplar->pool ?? 'jetstream',
                    $attributeExemplar->queue ?? '',
                    $attributeExemplar->processLag,
                );
                $process->nums = $nums;
                $process->name = $attributeExemplar->name . '-' . $attributeExemplar->subject;
                ProcessManager::register($process);
            }
        }


        $classes = $collector->getClassesByAnnotation(NatsJetstream::class);

        foreach ($classes as $className => $classAttributesInfo) {
            if (!$this->endpointFeatureFlagManager->checkIfEndpointEnabled($collector, $className)) {
                continue;
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
                $nums = $attributeExemplar->nums;
                $process = $this->createProcess(
                    app()->make($className),
                    $attributeExemplar->subject,
                    $attributeExemplar->pool ?? 'jetstream',
                    $attributeExemplar->queue ?? '',
                    $attributeExemplar->period,
                    $attributeExemplar->streamName,
                );
                $process->nums = $nums;
                $process->name = $attributeExemplar->name . '-' . $attributeExemplar->subject;
                ProcessManager::register($process);
            }
        }
    }

    private function createProcess(object $consumer, string $subject, string $poolName, string $queue = '', ?int $processLag = null, string $streamName = ''): AbstractProcess
    {
        return new NatsConsumerProcess(
            container: $this->container,
            endpoint: $consumer,
            subject: $subject,
            poolName: $poolName,
            queue: $queue,
            streamName: $streamName,
            processLag: $processLag
        );
    }
}
