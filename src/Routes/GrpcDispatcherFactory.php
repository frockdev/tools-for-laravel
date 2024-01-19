<?php

namespace FrockDev\ToolsForLaravel\Routes;

use FrockDev\ToolsForLaravel\Annotations\Grpc;
use FrockDev\ToolsForLaravel\AnnotationsCollector\Collector;
use FrockDev\ToolsForLaravel\AnnotationsObjectModels\Annotation;
use FrockDev\ToolsForLaravel\FeatureFlags\EndpointFeatureFlagManager;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Router\Router;

class GrpcDispatcherFactory extends DispatcherFactory
{

    private Collector $customCollector;
    private mixed $endpointFeatureFlagManager;

    public function __construct()
    {
        $this->customCollector = app()->make(Collector::class);
        $this->endpointFeatureFlagManager = app()->make(EndpointFeatureFlagManager::class);
        parent::__construct();
    }

    public function initConfigRoute()
    {
        Router::init($this);
        $grpcControllers = $this->customCollector->getClassesByAnnotation(Grpc::class);

        Router::addServer('grpc', function () use ($grpcControllers) {
            foreach ($grpcControllers as $className=>$info) {
                /**
                 * @var Annotation $annotation
                 */
                foreach ($info['classAnnotations'] as $annotationClassName => $annotation) {
                    if (!$this->endpointFeatureFlagManager->checkIfEndpointEnabled($this->customCollector, $className)) {
                        continue 2;
                    }
                    $route = $className::GRPC_ROUTE;
                    if ($annotationClassName==Grpc::class) {
                        Router::post('/'.ltrim($route, '/'), $className.'@__invoke');
                    }
                }
            }
        });
    }
}
