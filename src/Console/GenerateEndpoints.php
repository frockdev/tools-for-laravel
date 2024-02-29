<?php

namespace FrockDev\ToolsForLaravel\Console;

use FrockDev\ToolsForLaravel\Transport\AbstractMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use JetBrains\PhpStorm\Deprecated;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\PhpNamespace;

class GenerateEndpoints extends Command
{

    protected $signature = 'frock:generate-endpoints';

    private const ENDPOINT_DIR_NAME = 'Endpoints';
    private const ABSTRACT_ENDPOINT_DIR_NAME = 'AbstractEndpoints';

    /**
     * get all uses as full class names from file using regular expression
     * @param string $fileName
     * @return array
     */
    protected function getAllUsesFromFile(string $fileName): array {
        $content = file_get_contents($fileName);
        $pattern = '/use\s+(.*);/';
        preg_match_all($pattern, $content, $matches);
        return $matches[1];
    }

    /**
     * @param PhpNamespace $namespace
     * @param string $path
     * @return void
     */
    protected function putNamespaceToFile(PhpNamespace $namespace, string $path): string {
        if (file_exists($path)) {
            foreach ($this->getAllUsesFromFile($path) as $use) {
                $namespace->addUse($use);
            }
        }
        $file = new \Nette\PhpGenerator\PhpFile;
        $file->addComment('This file is generated by Frocker. Do not edit it manually.');
        $file->addNamespace($namespace);
        if (file_exists($path)) {
            unlink($path);
        } else {
            @mkdir(dirname($path), recursive: true);
        }
        file_put_contents($path, $file);
        return $file;
    }

    /**
     * @return void
     *  this function go recursively to app/Modules/[MODULE_NAME]/EndpointsNew
     *  and mark all classes as deprecated with #[Deprecated] attribute
     */
    private function markClassesInModulesAsDeprecated(string $what = self::ENDPOINT_DIR_NAME) {

        foreach (scandir(app_path() . '/Modules') as $module) {
            if ($module=='.' || $module=='..') continue;
            if (!is_dir(app_path() . '/Modules/'.$module)) continue;

            if (!file_exists(app_path() . '/Modules/'.$module.'/'.$what)) continue;
            $dir = new \RecursiveDirectoryIterator(app_path() . '/Modules/'.$module.'/'.$what);
            foreach (new \RecursiveIteratorIterator($dir) as $file) {
                if ($file->isFile()) {
                    $content = file_get_contents($file->getPathname());
                    $namespace = $this->getNamespaceString($content);
                    $phpNamespace = new PhpNamespace($namespace);
                    /** @var ClassType $classType */
                    $classType = ClassType::fromCode($content);
                    $phpNamespace->add($classType);
                    $classType->addAttribute(Deprecated::class);
                    $this->putNamespaceToFile($phpNamespace, $file->getPathname());
                }
            }
        }
    }
    public function handle() {
        $this->markClassesInModulesAsDeprecated();
        $this->markClassesInModulesAsDeprecated(self::ABSTRACT_ENDPOINT_DIR_NAME);
        $protoFiles = $this->getProtoFiles();
        foreach ($protoFiles as $protoFile) {
            $this->processFile($protoFile);
        }


    }

    private function getProtoFiles()
    {
        $protoFiles = [];
        $dir = new \RecursiveDirectoryIterator('../protoPhp');
        foreach (new \RecursiveIteratorIterator($dir) as $file) {
            if ($file->isFile()) {
                $protoFiles[] = $file->getPathname();
            }
        }
        return $protoFiles;
    }

    private function processFile(string $filePath)
    {
        $fileContent = file_get_contents($filePath);
        $namespaceName = $this->getNamespaceString($fileContent);
        $this->checkNamespace($namespaceName);
        $interfaceNames = $this->getInterfaceNames($fileContent);
        if (count($interfaceNames)===0 || is_null($interfaceNames)) {
            Log::error('No interface found in file: ' . $filePath);
            return;
        }
        if (count($interfaceNames) > 1) {
            throw new \Exception('More than one interface found in file: ' . $filePath);
        }
        $interfaceName = $interfaceNames[0];
        /** @var InterfaceType $interfaceType */
        $interfaceType = InterfaceType::from('\\'.$namespaceName.'\\'.$interfaceName);
        $this->createEndpointFromFile($interfaceType, $namespaceName);

    }

    /**
     * @param string $fileContent
     * @return string
     * get namespace from file using regular expression
     */
    private function getNamespaceString(string $fileContent): string
    {

        $namespace = '';
        $pattern = '/namespace\s+(.*);/';
        preg_match($pattern, $fileContent, $matches);
        if (isset($matches[1])) {
            $namespace = $matches[1];
        }
        return $namespace;

    }

    /**
     * @param string $fileContent
     * @return string[]|null
     * get interface names from file using regular expression
     */
    private function getInterfaceNames(string $fileContent): ?array
    {
        $pattern = '/interface\s+(.*)\s+{/';
        preg_match_all($pattern, $fileContent, $matches);
        if (isset($matches[1])) {
            return $matches[1];
        }
        return null;
    }

    private function createEndpointFromFile(InterfaceType $interfaceType, string $namespaceName)
    {
        if (!$interfaceType->getMethod('__invoke')) {
            Log::error('No handle method found in interface: ' . $interfaceType->getName());
            return;
        }
        $this->checkMethodHandle($interfaceType);
        $this->checkInterfaceName($interfaceType->getName());
        $abstractEndpointFullName = $this->createAbstractEndpoint($interfaceType, $namespaceName);

        $this->createRealEndpoint($interfaceType, $namespaceName, $abstractEndpointFullName);
    }

    private function createAbstractEndpoint(InterfaceType $interfaceType, string $namespaceName): string
    {
        $interfaceName = '\\'.$namespaceName.'\\'.$interfaceType->getName();

        $inputType = $interfaceType->getMethod('__invoke')->getParameters()['dto']->getType();
        $outputType = $interfaceType->getMethod('__invoke')->getReturnType();
        $generatedNamespace = $this->createNamespaceForGeneratedFromInterfaceNamespace($namespaceName, self::ABSTRACT_ENDPOINT_DIR_NAME);
        $abstractEndpointNamespace = new PhpNamespace($generatedNamespace);
        $endpointNameString = str_replace('Interface', '', $interfaceType->getName());
        $abstractEndpoint = new ClassType($endpointNameString.'Abstract');
        $abstractEndpoint->setAbstract();
        $abstractEndpoint->addComment('This file is generated by Frock.Dev. Do not edit it manually.');
        $abstractEndpoint->addConstant('ENDPOINT_INTERFACE_NAME', $interfaceName);
        $abstractEndpoint->addConstant('ENDPOINT_INPUT_TYPE', $inputType);
        $abstractEndpoint->addConstant('ENDPOINT_OUTPUT_TYPE', $outputType);
        $abstractEndpointNamespace->add($abstractEndpoint);
        $abstractEndpoint->addImplement($interfaceName);
        $abstractEndpoint->addMethod('getContext')
            ->setBody('return ContextStorage::get("endpoint_context_".get_called_class());')
            ->setPublic()
            ->setReturnType('array');
        $abstractEndpoint->addMethod('setContext')
            ->setBody('ContextStorage::set("endpoint_context_".get_called_class(), $context);')
            ->setPublic()
            ->setReturnType('void')
            ->addParameter('context');

        $abstractEndpoint->addProperty('callCountMetric')
            ->setType(\FrockDev\ToolsForLaravel\BaseMetrics\EndpointCallsCountMetric::class)
            ->setNullable()
            ->setValue(null)
            ->setProtected();
        $abstractEndpoint->addProperty('callDurationHistogramMetric')
            ->setType(\FrockDev\ToolsForLaravel\BaseMetrics\EndpointCallsDurationMetric::class)
            ->setNullable()
            ->setValue(null)
            ->setProtected();

        $metricLabel = $this->generateMetricLabels($interfaceType, $generatedNamespace);
        $abstractEndpoint->addMethod('__invoke')
            ->setBody($this->generateInvokeMethodBody($metricLabel))
            ->setPublic()
            ->setReturnType($outputType)
            ->addParameter('dto')
            ->setType($inputType);
        $runMethod = $abstractEndpoint->addMethod('handle');
        $runMethod->setReturnType($outputType);
        $runMethod->setProtected();
        $runMethod->setAbstract();
        $runMethod->addParameter('dto')
            ->setType($inputType);
        $abstractEndpointNamespace->addUse($inputType);
        $abstractEndpointNamespace->addUse($outputType);
        $abstractEndpointNamespace->addUse($interfaceName);

        $abstractEndpointNamespace->addUse(\FrockDev\ToolsForLaravel\Swow\ContextStorage::class);

        $this->putNamespaceToFile($abstractEndpointNamespace, app_path().'/'.str_replace('App/', '', str_replace('\\', '/', $generatedNamespace)).'/'.$endpointNameString.'Abstract.php');
        return '\\'.$generatedNamespace.'\\'.$abstractEndpoint->getName();
    }

    /**
     * @return string
     */
    public function generateInvokeMethodBody(string $metricLabel): string
    {
        $invoke = '';
        $invoke .= 'if (is_null($this->callCountMetric)) {' . "\n";
        $invoke .= '    $this->callCountMetric = \\FrockDev\\ToolsForLaravel\\BaseMetrics\\EndpointCallsCountMetric::declare();' . "\n";
        $invoke .= '}' . "\n";
        $invoke .= 'if (is_null($this->callDurationHistogramMetric)) {' . "\n";
        $invoke .= '    $this->callDurationHistogramMetric = \\FrockDev\\ToolsForLaravel\\BaseMetrics\\EndpointCallsDurationMetric::declare();' . "\n";
        $invoke .= '}' . "\n";
        $invoke .= '$this->callCountMetric->inc([\''.$metricLabel.'\']);'."\n";
        $invoke .= '$timeStart = microtime(true);'."\n";
        $invoke .= '$result = $this->handle($dto);' . "\n" .
            '$this->callDurationHistogramMetric->observe(microtime(true) - $timeStart, [\''.$metricLabel.'\']);'."\n".
            'return $result;';
        return $invoke;
    }

    private function checkNamespace(string $namespace)
    {
        $exploded = explode('\\', $namespace);
        if (count($exploded)!=4) {
            throw new \Exception('Namespace '.$namespace.' must contain exactly 4 segments. Example: Proto\\Service\\Subservice\\V1');
        }
        if (preg_match('/V\d+/', $exploded[3])===0) {
            throw new \Exception('Last segment of namespace '.$namespace.' must contain version. Example: V1');
        }
    }

    private function createNamespaceForGeneratedFromInterfaceNamespace(string $endpointNamespace, $what = self::ABSTRACT_ENDPOINT_DIR_NAME)
    {
        $exploded = explode('\\', $endpointNamespace);
        unset($exploded[0]);
        $namespace = 'App\\Modules\\'.$exploded[1].'\\';
        unset($exploded[1]);
        $namespace.=$what.'\\'.implode('\\', $exploded);
        return $namespace;
    }

    private function checkMethodHandle(InterfaceType $interfaceType)
    {
        if (!$interfaceType->getMethod('__invoke')->hasParameter('dto')) {
            throw new \Exception('Method handle in interface '.$interfaceType->getName().' must have parameter $dto of type '.AbstractMessage::class);
        }
        if (count($interfaceType->getMethod('__invoke')->getParameters())>1) {
            throw new \Exception('Method handle in interface '.$interfaceType->getName().' must have only one parameter $dto');
        }
        $returnType = $interfaceType->getMethod('__invoke')->getReturnType();
        if (!class_exists($returnType)) {
            throw new \Exception('Return type '.$returnType.' of method handle in interface '.$interfaceType->getName().' must be a class');
        }
        $returnTypeInstance = new $returnType;
        if (!is_subclass_of($returnTypeInstance, AbstractMessage::class)) {
            throw new \Exception('Return type '.$returnType.' of method handle in interface '.$interfaceType->getName().' must be a subclass of '.AbstractMessage::class);
        }
        $inputType = $interfaceType->getMethod('__invoke')->getParameters()['dto']->getType();
        if (!class_exists($inputType)) {
            throw new \Exception('Input type '.$inputType.' of method handle in interface '.$interfaceType->getName().' must be a class');
        }
        $inputTypeInstance = new $inputType;
        if (!is_subclass_of($inputTypeInstance, AbstractMessage::class)) {
            throw new \Exception('Input type '.$inputType.' of method handle in interface '.$interfaceType->getName().' must be a subclass of '.AbstractMessage::class);
        }
    }

    private function generateMetricLabels(InterfaceType $interfaceType, string $generatedNamespace)
    {
        $exploded = explode('\\', $generatedNamespace);
        $metricLabel = $exploded[2].'_'.$interfaceType->getName();
        return $metricLabel;
    }

    private function createRealEndpoint(InterfaceType $interfaceType, string $namespaceName, string $abstractEndpointFullName)
    {
        $interfaceName = '\\'.$namespaceName.'\\'.$interfaceType->getName();
        $inputType = $interfaceType->getMethod('__invoke')->getParameters()['dto']->getType();
        $outputType = $interfaceType->getMethod('__invoke')->getReturnType();
        $generatedNamespaceString = $this->createNamespaceForGeneratedFromInterfaceNamespace($namespaceName, self::ENDPOINT_DIR_NAME);
        $realEndpointNamespace = new PhpNamespace($generatedNamespaceString);
        $endpointNameString = str_replace('Interface', '', $interfaceType->getName());
        $realEndpointPathString = app_path().'/'.str_replace('App/', '', str_replace('\\', '/', $generatedNamespaceString)).'/'.$endpointNameString.'.php';
        if (file_exists($realEndpointPathString)) {
            if ($this->isAbstractEndpointDeprecated($endpointNameString, $generatedNamespaceString)) {
                return;
            } else {
                /** @var ClassType $realEndpoint */
                $realEndpoint = ClassType::fromCode(file_get_contents($realEndpointPathString));
                $realEndpointNamespace->add($realEndpoint);
                $newAttributesCollection = [];
                foreach ($realEndpoint->getAttributes() as $attribute) {
                    if ($attribute->getName() == Deprecated::class) {
                        continue;
                    }
                    $newAttributesCollection[] = $attribute;
                }
                $realEndpoint->setAttributes($newAttributesCollection);
                $this->putNamespaceToFile($realEndpointNamespace, $realEndpointPathString);
                return;
            }

        } else {
            $realEndpoint = new ClassType($endpointNameString);
        }
        $realEndpoint->addComment('This class based on '.$interfaceName);
        $realEndpoint->addImplement($interfaceName);
        $realEndpoint->setExtends($abstractEndpointFullName);
        $realEndpointNamespace->addUse($abstractEndpointFullName);
        $realEndpoint->addMethod('handle')
            ->setBody('throw new \Exception("Not implemented");')
            ->setReturnType($outputType)
            ->setProtected()
            ->addParameter('dto')
            ->setType($inputType);

        $realEndpointNamespace->addUse($inputType);
        $realEndpointNamespace->addUse($outputType);
        $realEndpointNamespace->addUse($interfaceName);
        $realEndpointNamespace->add($realEndpoint);

        $this->putNamespaceToFile($realEndpointNamespace, app_path().'/'.str_replace('App/', '', str_replace('\\', '/', $generatedNamespaceString)).'/'.$realEndpoint->getName().'.php');
    }

    private function checkInterfaceName(?string $getName)
    {
        if (!preg_match('/.*V\d+Interface/', $getName)) {
            throw new \Exception('Interface name '.$getName.' must be like SomethingDoingEndpointV1Interface');
        }
    }

    private function isAbstractEndpointDeprecated(string $endpointNameString, string $generatedNamespaceString)
    {
        $pathToAbstract = app_path().'/'
            .str_replace('App/', '',
                str_replace(self::ENDPOINT_DIR_NAME, self::ABSTRACT_ENDPOINT_DIR_NAME,
                    str_replace('\\', '/', $generatedNamespaceString)
                )
            ).'/'.$endpointNameString.'Abstract.php';
        $content = file_get_contents($pathToAbstract);
        $classType = ClassType::fromCode($content);
        foreach ($classType->getAttributes() as $attribute) {
            if ($attribute->getName() == Deprecated::class) {
                return true;
            }
        }
        return false;
    }
}
