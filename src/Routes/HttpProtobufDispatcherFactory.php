<?php

namespace FrockDev\ToolsForLaravel\Routes;

use FrockDev\ToolsForLaravel\AnnotationsCollector\Collector;
use FrockDev\ToolsForLaravel\AnnotationsObjectModels\Annotation;
use FrockDev\ToolsForLaravel\Annotations\Grpc;
use FrockDev\ToolsForLaravel\Annotations\Http;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Router\Router;
use ReflectionClass;

class HttpProtobufDispatcherFactory extends DispatcherFactory
{
    private Collector $customCollector;

    public function __construct()
    {
        $this->customCollector = app()->make(Collector::class);
        parent::__construct();
    }
    public function initConfigRoute()
    {

        Router::init($this);
        $httpController = $this->customCollector->getClassesByAnnotation(Http::class);

        Router::addServer('http', function () use ($httpController) {
            /**
             * @var string $class
             * @var Http $annotation
             */
            foreach ($httpController as $class=>$annotations) {
                /** @var Annotation $annotation */
                foreach ($annotations['classAnnotations'] as $annotationClassName => $annotation) {
                    if ($annotationClassName == Http::class) {
                        $route = $annotation->getArguments()[1];
                        if (strtolower($annotation->getArguments()[0])=='get') {
                            Router::get('/'.ltrim($route,'/'), $class.'@__invoke');
                        } else {
                            Router::post('/'.ltrim($route,'/'), $class.'@__invoke');
                        }
                    }
                }
            }
        });
    }
}