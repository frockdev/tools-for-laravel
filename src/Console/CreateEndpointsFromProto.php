<?php

namespace FrockDev\ToolsForLaravel\Console;

use FrockDev\ToolsForLaravel\InterceptorInterfaces\PostInterceptorInterface;
use FrockDev\ToolsForLaravel\InterceptorInterfaces\PreInterceptorInterface;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\PhpNamespace;

class CreateEndpointsFromProto extends Command
{

    protected $signature = 'frock:create-endpoints-from-proto';

    /**
     * @param ClassType $innerGrpcController
     * @return void
     */
    public function innerGrpcControllerConstructorCreate(ClassType $innerGrpcController): void
    {
        $constructor = $innerGrpcController->addMethod('__construct');
        $constructor->addParameter('container')
            ->setType(Container::class);
        $constructor->setBody(
            '$this->container = $container;'
        );
        $innerGrpcController->addProperty('container')
            ->setType(Container::class)
            ->setPrivate();
    }

    /**
     * @param ClassType $innerGrpcController
     * @param \Nette\PhpGenerator\Method $method
     * @param string $transportFullName
     * @return void
     */
    public function abstractEndpointMethodCreate(ClassType $innerGrpcController, \Nette\PhpGenerator\Method $method, string $transportFullName): void
    {
        $innerControllerMethod = $innerGrpcController->addMethod($method->getName());
        $innerControllerMethod->setPublic();
        $innerControllerMethod->setReturnType($method->getReturnType());
        $innerControllerMethod->setParameters($method->getParameters());
        $innerControllerMethod->setBody(
            '/** @var \\App\\Modules\\' . $transportFullName . ' $transportController */' . "\n" .
            '$transportController = $this->container->get(\\App\\Modules\\' . $transportFullName . '::class);' . "\n" .
            '$transportController->context = $ctx->getValues();' . "\n" .
            'return $transportController($in);'
        );
    }

    /**
     * @param ClassType $newAbstractClass
     * @return void
     */
    public function createInterceptorsArrays(ClassType $newAbstractClass): void
    {
        $newAbstractClass->addProperty('preInterceptors')
            ->setType('array')
            ->setPrivate()
            ->setValue([]);
        $addPreInterceptorMethod = $newAbstractClass->addMethod('addPreInterceptor');
        $addPreInterceptorMethod->addParameter('interceptor')
            ->setType(PreInterceptorInterface::class);
        $addPreInterceptorMethod->setBody(
            '$this->preInterceptors[] = $interceptor;'
        );

        $newAbstractClass->addProperty('postInterceptors')
            ->setType('array')
            ->setPrivate()
            ->setValue([]);
        $addPostInterceptorMethod = $newAbstractClass->addMethod('addPostInterceptor');
        $addPostInterceptorMethod->addParameter('interceptor')
            ->setType(PostInterceptorInterface::class);
        $addPostInterceptorMethod->setBody(
            '$this->postInterceptors[] = $interceptor;'
        );
    }

    /**
     * @param ClassType $newAbstractClass
     * @param \Nette\PhpGenerator\Method $method
     * @return \Nette\Utils\Type|string
     * @throws \Exception
     */
    public function createInvokeMethod(ClassType $newAbstractClass, \Nette\PhpGenerator\Method $method): \Nette\Utils\Type|string
    {
        $invokeMethod = $newAbstractClass->addMethod('__invoke')->setPublic();
        foreach ($method->getParameters() as $parameter) {
            if ($parameter->getName() != 'in') continue;
            $currentType = $parameter->getType();
            $newMethodParameterType = $currentType;
            break;
        }
        if (empty($newMethodParameterType)) {
            throw new \Exception('No parameter type found');
        }
        $invokeMethod->addParameter('dto')
            ->setType($newMethodParameterType);

        $invokeMethod->setBody(
            $this->generateInvokeMethodBody()
        );

        $currentReturnType = $method->getReturnType();
        $invokeMethod->setReturnType($currentReturnType);

        $this->fixTypeConstructorAttributeInComment($newMethodParameterType);
        $this->fixTypeConstructorAttributeInComment($currentReturnType);

        return $newMethodParameterType;
    }

    /**
     * @return string
     */
    public function generateInvokeMethodBody(): string
    {
        return 'foreach ($this->preInterceptors as $interceptor) {' . "\n" .
            '    /** @var \\' . PreInterceptorInterface::class . ' $interceptor */' . "\n" .
            '      $interceptor->intercept($this->context, $dto);' . "\n" .
            '}' . "\n" .
            '$result = $this->run($dto);' . "\n" .
            'foreach ($this->postInterceptors as $interceptor) {' . "\n" .
            '    /** @var \\' . PostInterceptorInterface::class . ' $interceptor */' . "\n" .
            '      $interceptor->intercept($this->context, $result, $result);' . "\n" .
            '}' . "\n" .
            'return $result;';
    }

    /**
     * @param ClassType $newAbstractClass
     * @param string|\Nette\Utils\Type $newMethodParameterType
     * @param \Nette\PhpGenerator\Method $method
     * @return void
     */
    public function createRunMethod(ClassType $newAbstractClass, string|\Nette\Utils\Type $newMethodParameterType, \Nette\PhpGenerator\Method $method): void
    {
        $runMethod = $newAbstractClass->addMethod('run')->setProtected();
        $runMethod->addParameter('dto')
            ->setType($newMethodParameterType);
        $runMethod->setAbstract();
        $runMethod->setReturnType($method->getReturnType());
    }

    /**
     * @param ClassType $newAbstractClass
     * @param \Nette\PhpGenerator\Constant $baseRoute
     * @param \Nette\PhpGenerator\Method $method
     * @return void
     */
    public function addGrpcRouteConstant(ClassType $newAbstractClass, \Nette\PhpGenerator\Constant $baseRoute, \Nette\PhpGenerator\Method $method): void
    {
        $newAbstractClass->addConstant('GRPC_ROUTE',
            str_replace('"', '', $baseRoute->getValue()) . '.' . $method->getName()
        );
    }

    /**
     * @param ClassType $newAbstractClass
     * @param mixed $grpcInterfaceNamespace
     * @param InterfaceType $grpcInterface
     * @param \Nette\PhpGenerator\Method $method
     * @param PhpNamespace $innerControllerNamespace
     * @param ClassType $innerGrpcController
     * @param string|\Nette\Utils\Type $newMethodParameterType
     * @return void
     */
    public function addConstants(ClassType $newAbstractClass, mixed $grpcInterfaceNamespace, InterfaceType $grpcInterface, \Nette\PhpGenerator\Method $method, PhpNamespace $innerControllerNamespace, ClassType $innerGrpcController, string|\Nette\Utils\Type $newMethodParameterType): void
    {
        $newAbstractClass->addConstant('GRPC_INTERFACE_NAME', $grpcInterfaceNamespace . '\\' . $grpcInterface->getName());
        $newAbstractClass->addConstant('GRPC_INTERFACE_METHOD_NAME', $method->getName());
        $newAbstractClass->addConstant('GRPC_INTERFACE_REALIZATION', '\\' . $innerControllerNamespace->getName() . '\\' . $innerGrpcController->getName());
        $newAbstractClass->addConstant('GRPC_INPUT_TYPE', $newMethodParameterType);
        $newAbstractClass->addConstant('GRPC_OUTPUT_TYPE', $method->getReturnType());
    }

    /**
     * @param mixed $serviceDir
     * @param mixed $versionDir
     * @return void
     */
    public function clear(string $serviceDir, string $subServiceDir, string $versionDir): void
    {
        shell_exec('rm -rf ' . '/var/www/php/app/Modules/' . $serviceDir . '/InnerGrpcControllers/' .$subServiceDir . '/' . $versionDir);
        shell_exec('rm -rf ' . '/var/www/php/app/Modules/' . $serviceDir . '/AbstractEndpoints/' . $subServiceDir . '/' . $versionDir);
    }

    private function getGrpcInterfaceNamespace(string $fileContent) {
        $matches = [];
        preg_match('/^namespace (.*);$/m', $fileContent, $matches);
        return $matches[1];
    }

    public function createInnerGrpcControllerNamespace(string $serviceName, string $subServiceName, string $versionName): PhpNamespace {
        return new PhpNamespace('App\\Modules\\'.$serviceName.'\\InnerGrpcControllers\\'.$subServiceName.'\\'.$versionName);
    }

    public function handle() {
        foreach (scandir('/var/www/php/protoGenerated/') as $serviceDir) {
            if ($serviceDir === '.' || $serviceDir === '..' || !is_dir('/var/www/php/protoGenerated/'.$serviceDir)) continue;
            // это сервисы
            foreach (scandir('/var/www/php/protoGenerated/'.$serviceDir) as $subServiceDir) {
                if ($subServiceDir === '.' || $subServiceDir === '..' || !is_dir('/var/www/php/protoGenerated/'.$serviceDir.'/'.$subServiceDir)) continue;
                // это подсервисы
                foreach (scandir('/var/www/php/protoGenerated/'.$serviceDir.'/'.$subServiceDir) as $versionDir) {
                    // это версии обслуживаемых подсеврисов
                    if ($versionDir === '.' || $versionDir === '..' || !is_dir('/var/www/php/protoGenerated/' . $serviceDir.'/'. $subServiceDir . '/' . $versionDir)) continue;

                    $interfacePaths = [];
                    echo 'lets scan '.'/var/www/php/protoGenerated/' . $serviceDir .'/'. $subServiceDir . '/' . $versionDir."\n";
                    foreach (scandir('/var/www/php/protoGenerated/' . $serviceDir .'/'. $subServiceDir . '/' . $versionDir) as $filePath) {
                        if ($filePath === '.' || $filePath === '..' || is_dir('/var/www/php/protoGenerated/' . $serviceDir.'/'. $subServiceDir . '/' . $versionDir . '/' . $filePath)) continue;
                        if (str_ends_with($filePath, 'Interface.php')) {
                            $interfacePaths[] = '/var/www/php/protoGenerated/' . $serviceDir .'/'. $subServiceDir . '/' . $versionDir . '/' . $filePath;
                        }
                    }
                    if (empty($interfacePaths)) {
                        throw new \Exception('Cannot find any interface in /var/www/php/protoGenerated/' . $serviceDir.'/'. $subServiceDir . '/' . $versionDir . '/');
                    }
                    $this->clear($serviceDir, $subServiceDir, $versionDir);
                    foreach ($interfacePaths as $interfacePath) {
                        $fileContent = file_get_contents($interfacePath);
                        /** @var InterfaceType $grpcInterface */
                        $grpcInterface = InterfaceType::fromCode($fileContent);

                        $grpcInterfaceNamespace = $this->getGrpcInterfaceNamespace($fileContent);

                        $innerGrpcController = new ClassType(str_replace('Interface', '', $grpcInterface->getName()) . 'InnerController');
                        $innerGrpcController->addComment('This file is generated by Frock.Dev. Do not edit it manually.');
                        $innerControllerNamespace = $this->createInnerGrpcControllerNamespace($serviceDir, $subServiceDir, $versionDir);
                        $innerControllerNamespace->add($innerGrpcController);
                        $innerGrpcController->setFinal();
                        $innerGrpcController->setImplements([$grpcInterfaceNamespace . '\\' . $grpcInterface->getName()]);

                        $this->innerGrpcControllerConstructorCreate($innerGrpcController);

                        foreach ($grpcInterface->getMethods() as $method) {

                            $abstractEndpointClassName = str_replace('Interface', '', $grpcInterface->getName()) . $method->getName() . 'AbstractEndpoint';
                            $transportFullName = $serviceDir . '\\AbstractEndpoints\\' .$subServiceDir.'\\' . $versionDir . '\\' . $abstractEndpointClassName;

                            $this->abstractEndpointMethodCreate($innerGrpcController, $method, $transportFullName);

                            $newAbstractEndpointNamespace = new PhpNamespace('App\\Modules\\' . $serviceDir . '\\AbstractEndpoints\\' .$subServiceDir . '\\' . $versionDir);
                            $newAbstractClass = new ClassType(
                                $abstractEndpointClassName
                            );
                            $newAbstractClass->addComment('This file is generated by Frock.Dev. Do not edit it manually.');
                            $newAbstractClass->setAbstract();
                            //                        $this->injectBusManager($newAbstractClass);
                            $newAbstractClass->addProperty('context')
                                ->setType('array');

                            $this->createInterceptorsArrays($newAbstractClass);

                            $newAbstractEndpointNamespace->add($newAbstractClass);

                            $invokeMethodDtoType = $this->createInvokeMethod($newAbstractClass, $method);

                            $this->createRunMethod($newAbstractClass, $invokeMethodDtoType, $method);

                            $this->addConstants($newAbstractClass, $grpcInterfaceNamespace, $grpcInterface, $method, $innerControllerNamespace, $innerGrpcController, $invokeMethodDtoType);

                            $allConstants = $grpcInterface->getConstants();
                            $baseRoute = $allConstants['NAME'];

                            $this->addGrpcRouteConstant($newAbstractClass, $baseRoute, $method);

                            $this->createEndpointRealizationIfNotExists(
                                $newAbstractEndpointNamespace->getName(),
                                $newAbstractClass->getName(),
                                $method,
                            );

                            $this->putNamespaceToFile($newAbstractEndpointNamespace, '/var/www/php/app/Modules/' . $serviceDir. '/AbstractEndpoints/' . $subServiceDir . '/' . $versionDir . '/' . $newAbstractClass->getName() . '.php');
                            $this->putNamespaceToFile($innerControllerNamespace, '/var/www/php/app/Modules/' . $serviceDir. '/InnerGrpcControllers/' . $subServiceDir . '/'  . $versionDir . '/' . $innerGrpcController->getName() . '.php');
                        }
                    }
                }
            }
        }
        return 0;

    }

    /**
     * @param PhpNamespace $namespace
     * @param string $path
     * @return void
     */
    protected function putNamespaceToFile(PhpNamespace $namespace, string $path): string {
        $file = new \Nette\PhpGenerator\PhpFile;
        $file->addComment('This file is generated by Frocker. Do not edit it manually.');
        $file->addNamespace($namespace);

        @mkdir(dirname($path), recursive: true);
        file_put_contents($path, $file);

        return $file;
    }


    private function createEndpointRealizationIfNotExists(string $namespaceName, string $abstractClassName, \Nette\PhpGenerator\Method $method)
    {
        $explodedNamespace = explode('\\', $namespaceName);
        unset($explodedNamespace[0]);
        unset($explodedNamespace[1]);
        $explodedNamespace[3] = 'Endpoints';
        $realizationClassName = str_replace('Abstract', '', $abstractClassName);
        $realizationFilePath = '/var/www/php/app/Modules/'.implode('/', $explodedNamespace).'/'.$realizationClassName.'.php';
        if (file_exists($realizationFilePath))  {
            return;
        }

        $realizationNamespace = 'App\\Modules\\'.implode('\\', $explodedNamespace);

        $realizationNamespace = new PhpNamespace($realizationNamespace);

        $class = new ClassType($realizationClassName);

        $inputParameters = $method->getParameters();
        $usedParameter = $inputParameters['in'];

        $runMethod = $class->addMethod('run');
        $runMethod->setReturnType($method->getReturnType());
        $runMethod->addParameter('dto')
            ->setType($usedParameter->getType());
        $realizationNamespace->addUse($usedParameter->getType());
        $realizationNamespace->addUse($method->getReturnType());
        $realizationNamespace->addUse('\\'.$namespaceName.'\\'.$abstractClassName);
        $runMethod->setBody('throw new \Exception(\'Not implemented yet\');');
        $runMethod->setProtected();

        $class->setExtends('\\'.$namespaceName.'\\'.$abstractClassName);



        $realizationNamespace->add($class);

        $this->putNamespaceToFile($realizationNamespace, $realizationFilePath);

    }

    private function fixTypeConstructorAttributeInComment(string $type)
    {
        $reflector = new \ReflectionClass($type);
        $this->fixRepeatedFieldTypes($reflector->getFileName(), $reflector->getNamespaceName());
        $this->makeObjectCorrectlySerializable($reflector->getFileName(), $reflector->getNamespaceName());

        $content =  file_get_contents($reflector->getFileName());
        $content = str_replace('@type', '//type', $content);
        file_put_contents($reflector->getFileName(), $content);
    }

    private function fixRepeatedFieldTypes(string $fileName, string $namespaceName)
    {
        /** @var ClassType $classType */
        $classType = ClassType::fromCode(file_get_contents($fileName));
        $namespace =  new PhpNamespace($namespaceName);
        $namespace->add($classType);

        $shouldReWrite = false;
        foreach ($classType->getMethods() as $potentialSetMethod) {
            if (str_starts_with($potentialSetMethod->getName(), 'set')) {
                $shouldReWrite = true;
                $doc = $potentialSetMethod->getComment();
                preg_match('/@param\s+([^\s]+)/', $doc, $matches);
                if (str_contains($matches[1], 'Google\\Protobuf\\Internal\\RepeatedField')) {
                    $fieldName = ucfirst(str_replace('set', '', $potentialSetMethod->getName()));
                    foreach ($potentialSetMethod->getParameters() as $parameter) {
                        $parameter->setType('array|\\Google\\Protobuf\\Internal\\RepeatedField');
                    }
                    foreach ($classType->getProperties() as $property) {
                        if (ucfirst($property->getName()) === $fieldName) {
                            $doc = '@var '.$matches[1].' '.$property->getName()."\n";
                            $property->setComment($doc);
                        }
                    }
                    foreach ($classType->getMethods() as $potentialGetMethod) {
                        if ($potentialGetMethod->getName()=='get'.$fieldName) {
                            $doc = '@return '.$matches[1]."\n";
                            $potentialGetMethod->setComment($doc);
                            $potentialGetMethod->setReturnType('array|\\Google\\Protobuf\\Internal\\RepeatedField');
                        }
                    }
                }
            }
        }
        if ($shouldReWrite) {
            $this->putNamespaceToFile($namespace, $fileName);
        }

    }

    private function makeObjectCorrectlySerializable(string $fileName, string $namespaceName)
    {
        /** @var ClassType $classType */
        $classType = ClassType::fromCode(file_get_contents($fileName));
        $classType->setImplements(['\\JsonSerializable']);
        $namespace =  new PhpNamespace($namespaceName);
        $namespace->add($classType);
        if ($classType->hasMethod('jsonSerialize')) return;
        $serializableMethod = $classType->addMethod('jsonSerialize');
        $serializableMethod->setReturnType('mixed');
        $serializableMethod->setPublic();
        $serializableMethod->setComment('This method created only for OA doc generation');

        $serializableBody = '$result = [];'.PHP_EOL;
        foreach ($classType->getMethods() as $method) {
            if (str_starts_with($method->getName(), 'get')) {
                if ($classType->hasProperty(lcfirst(str_replace('get', '', $method->getName())))) {
                    $propertyName = lcfirst(str_replace('get', '', $method->getName()));
                } else {
                    $propertyName = ucfirst(str_replace('get', '', $method->getName()));
                }
                $getterDoc = $method->getComment();
                if (str_contains($getterDoc, '[]')) {
                    $serializableBody.= 'if (($this->'.$method->getName().'()->count())==0) {'.PHP_EOL;
                    //need to take type from doc
                    $matches = [];
                    preg_match('/@return\s+(.*?)\[]/', $getterDoc, $matches);
                    $arrayType = $matches[1];
                    if (str_contains($arrayType, '\\')) {
                        $serializableBody.= '   $createdArray = ['.PHP_EOL;
                        $serializableBody.= '       new '.$arrayType.'(),'.PHP_EOL;
                        $serializableBody.= '       new '.$arrayType.'(),'.PHP_EOL;
                        $serializableBody.= '    ];'.PHP_EOL;
//                        $reflection = new \ReflectionClass($arrayType);
//                        $this->makeObjectCorrectlySerializable($reflection->getFileName(), $reflection->getNamespaceName());
                        $this->fixTypeConstructorAttributeInComment($arrayType);

                    } elseif ($arrayType=='string') {
                        $serializableBody.= '   $createdArray = ['.PHP_EOL;
                        $serializableBody.= '       "str1",'.PHP_EOL;
                        $serializableBody.= '       "str2"'.PHP_EOL;
                        $serializableBody.= '    ];'.PHP_EOL;
                    } elseif ($arrayType=='int') {
                        $serializableBody.= '   $createdArray = ['.PHP_EOL;
                        $serializableBody.= '       1,'.PHP_EOL;
                        $serializableBody.= '       2,'.PHP_EOL;
                        $serializableBody.= '    ];'.PHP_EOL;
                    }
                    $serializableBody.= '$this->set'.$propertyName.'($createdArray);'.PHP_EOL;
                    $serializableBody.= '}'.PHP_EOL;
                    $serializableBody.= 'foreach ($this->'.$method->getName().'() as $item) {'.PHP_EOL;
                    $serializableBody.= '   if (is_object($item) && method_exists($item, \'jsonSerialize\')) {'.PHP_EOL;
                    $serializableBody.='        $result[\''.$propertyName.'\'][] = $item->jsonSerialize();'.PHP_EOL;
                    $serializableBody.='    } else {'.PHP_EOL;
                    $serializableBody.='        $result[\''.$propertyName.'\'][] = $item;'.PHP_EOL;
                    $serializableBody.='    }'.PHP_EOL;
                    $serializableBody.= '}';
                } else {
                    $serializableBody.= 'if (is_object($this->'.$method->getName().'()) && method_exists($this->'.$method->getName().'(), \'jsonSerialize\')) {
                        $result[\''.$propertyName.'\'] = $this->'.$method->getName().'()->jsonSerialize();
                    } else {
                        $result[\''.$propertyName.'\'] = $this->'.$method->getName().'();
                    }';
                }
            }
        }
        $serializableBody .= 'return $result;';
        $serializableMethod->setBody($serializableBody);
        $this->putNamespaceToFile($namespace, $fileName);

    }
}
