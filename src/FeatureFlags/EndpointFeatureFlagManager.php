<?php

namespace FrockDev\ToolsForLaravel\FeatureFlags;

use FrockDev\ToolsForLaravel\Annotations\EndpointFeatureFlag;
use FrockDev\ToolsForLaravel\AnnotationsCollector\Collector;
use FrockDev\ToolsForLaravel\AnnotationsObjectModels\Annotation;
use Illuminate\Support\Facades\Log;

class EndpointFeatureFlagManager
{
    public function checkIfEndpointEnabled(Collector $collector, string $className) {
        $annotationsByClass = $collector->getAnnotationsByClassName(ltrim($className, '\\'));
        /**
         * @var Annotation $annotationInfo
         */
        foreach ($annotationsByClass['classAnnotations'] as $annotationClassName=>$annotationInfo) {
            if ($annotationClassName == EndpointFeatureFlag::class) {
                $annotationInstance = new $annotationClassName(...$annotationInfo->getArguments());
                $endpointEnabled = (bool)env($annotationInstance->name, $annotationInstance->default);
                if ($endpointEnabled === false) {
                    Log::info('Endpoint ' . $className . ' is disabled by feature flag '.$annotationInstance->name);
                    return false;
                }
            }
        }
        return true;
    }
}
