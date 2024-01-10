<?php

namespace FrockDev\ToolsForLaravel\EventLIsteners;

use FrockDev\ToolsForLaravel\Annotations\Nats;
use FrockDev\ToolsForLaravel\Annotations\NatsJetstream;
use FrockDev\ToolsForLaravel\AnnotationsCollector\Collector;
use FrockDev\ToolsForLaravel\NatsJetstream\NatsConsumerManager;
use FrockDev\ToolsForLaravel\Support\AppModeResolver;
use function Hyperf\Support\env;

class RunNatsListener
{

    private AppModeResolver $appModeResolver;

    public function __construct(AppModeResolver $appModeResolver)
    {

        $this->appModeResolver = $appModeResolver;
    }

    public function handle() {
        /** @var NatsConsumerManager $consumerManager */
        $consumerManager = app()->get(\Hyperf\Nano\App::class)->getContainer()->make(NatsConsumerManager::class);
        /** @var Collector $collector */
        $collector = app()->get(Collector::class);

        $needNats = ((count($collector->getClassesByAnnotation(Nats::class))>0)
                || (count($collector->getClassesByAnnotation(NatsJetstream::class))>0))
            && (
                $this->appModeResolver->isNatsAllowedToRun()
            );
        if ($needNats) {
            $consumerManager->run();
        }
    }
}
