<?php

namespace FrockDev\ToolsForLaravel\Console;

use Illuminate\Console\Command;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

class AddToArrayToGrpcObjects extends Command
{
    protected $signature = 'frock:add-to-array-to-grpc-objects';

    public function handle()
    {

        foreach (scandir('/var/www/php/protoGenerated/') as $serviceDir) {
            if ($serviceDir === '.' || $serviceDir === '..' || !is_dir('/var/www/php/protoGenerated/' . $serviceDir)) continue;
            // это сервисы

            foreach (scandir('/var/www/php/protoGenerated/'.$serviceDir) as $subServiceDir) {
                if ($subServiceDir === '.' || $subServiceDir === '..' || !is_dir('/var/www/php/protoGenerated/' . $serviceDir.'/'.$subServiceDir)) continue;
                // это сервисы

            foreach (scandir('/var/www/php/protoGenerated/' . $serviceDir.'/'.$subServiceDir) as $versionDir) {
                // это версии обслуживаемых подсеврисов
                if ($versionDir === '.' || $versionDir === '..' || !is_dir('/var/www/php/protoGenerated/' . $serviceDir.'/'.$subServiceDir . '/' . $versionDir)) continue;


                foreach (scandir('/var/www/php/protoGenerated/' . $serviceDir.'/'.$subServiceDir . '/' . $versionDir) as $filePath) {
                    if ($filePath === '.' || $filePath === '..' || is_dir('/var/www/php/protoGenerated/' . $serviceDir. '/'.$subServiceDir . '/' . $versionDir . '/' . $filePath)) continue;

                    $file = file_get_contents('/var/www/php/protoGenerated/' . $serviceDir. '/'.$subServiceDir . '/' . $versionDir . '/' . $filePath);
                    $namespaceRegularExp = '/namespace (.*);/';
                    preg_match($namespaceRegularExp, $file, $matches);
                    if (!isset($matches[1])) {
                        throw new \Exception('Namespace not found in proto file. Don\'t forget write in file "namespace <namespace_name>;"');
                    }
                    $namespace = $matches[1];

                    /** @var ClassType $class */
                    $class = ClassType::fromCode($file);

                    if ($class->getExtends() != \Google\Protobuf\Internal\Message::class) {
                        continue;
                    }

                    $this->makeObjectCorrectlySerializable('/var/www/php/protoGenerated/' . $serviceDir.'/'.$subServiceDir . '/' . $versionDir . '/' . $filePath, $namespace);

                }
            }
        }
        }
    }

    private function makeObjectCorrectlySerializable(string $fileName, string $namespaceName)
    {
        /** @var ClassType $classType */
        $classType = ClassType::fromCode(file_get_contents($fileName));
        $namespace =  new PhpNamespace($namespaceName);
        $namespace->add($classType);
        if ($classType->hasMethod('convertToArray')) return;
        $serializableMethod = $classType->addMethod('convertToArray');
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
                    $serializableBody.= '   if (is_object($item) && method_exists($item, \'convertToArray\')) {'.PHP_EOL;
                    $serializableBody.='        $result[\''.$propertyName.'\'][] = $item->convertToArray();'.PHP_EOL;
                    $serializableBody.='    } else {'.PHP_EOL;
                    $serializableBody.='        $result[\''.$propertyName.'\'][] = $item;'.PHP_EOL;
                    $serializableBody.='    }'.PHP_EOL;
                    $serializableBody.= '}';
                } else {
                    $serializableBody.= 'if (is_object($this->'.$method->getName().'()) && method_exists($this->'.$method->getName().'(), \'convertToArray\')) {
                        $result[\''.$propertyName.'\'] = $this->'.$method->getName().'()->convertToArray();
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
}
