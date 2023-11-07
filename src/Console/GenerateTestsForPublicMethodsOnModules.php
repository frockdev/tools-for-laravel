<?php

namespace FrockDev\ToolsForLaravel\Console;

use FrockDev\ToolsForLaravel\Annotations\ShouldBeTested;
use Illuminate\Console\Command;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

class GenerateTestsForPublicMethodsOnModules extends Command
{
    protected $signature = 'frock:generate-tests-for-public-methods-on-modules';

    public function handle() {
        $modulesDir = app_path().'/Modules';
        $files = glob("$modulesDir/{,*/,*/*/,*/*/*/}*.php", GLOB_BRACE);
        foreach ($files as $file) {
            if (is_dir($file)) continue;
            $this->generateTestsForFile($file);
        }
    }

    private function getAllUsesFromFile($filePath) {
        if (!file_exists($filePath)) return [];
        $uses = [];
        $file = file_get_contents($filePath);
        preg_match_all('/use\s+(.+);/', $file, $matches);
        foreach ($matches[1] as $match) {
            $uses[] = $match;
        }
        return $uses;
    }

    private function generateTestsForFile(string $file)
    {
        if (str_ends_with($file, 'InnerController.php')) return;
        if (str_ends_with($file, 'AbstractEndpoint.php')) return;
        preg_match('/namespace\s+(.+);/', file_get_contents($file), $matches);

        $namespaceName = $matches[1];
        $classType = ClassType::fromCode(file_get_contents($file));
        if (!($classType instanceof ClassType)) return;
        $reflectionClass = new \ReflectionClass($namespaceName.'\\'.$classType->getName());
        $attributes = $reflectionClass->getAttributes();
        $testsForMethods = [];
        foreach ($attributes as $attribute) {
            if ($attribute->getName()!==\FrockDev\ToolsForLaravel\Annotations\ShouldBeTested::class) continue;
            /** @var ShouldBeTested $instance */
            $instance = $attribute->newInstance();
            $testsForMethods = array_merge($testsForMethods, $instance->methods);
        }

        foreach ($testsForMethods as $testMethod) {
            $this->generateTestForMethod($testMethod, $file, $classType->getName());
        }
    }

    private function generateTestForMethod(string $method, string $file, string $className)
    {
        $pathToTest = 'tests/Feature/App/'.str_replace(app_path(), '', $file);
        $pathToTest = substr($pathToTest, 0, -4).'Test.php';

        $uses = $this->getAllUsesFromFile($pathToTest);

        if (!file_exists($pathToTest)) {
            $classType = new ClassType($className.'Test');
            $namespace = new PhpNamespace('Tests\\Feature\\App'.str_replace('/', '\\', str_replace(app_path(), '', dirname($file))));
            foreach ($uses as $use) {
                $namespace->addUse($use);
            }
            $namespace->add($classType);
            $classType->setExtends('\\Tests\\TestCase');
            $namespace->addUse('\\Tests\\TestCase');
        } else {
            /** @var ClassType $classType */
            $classType = ClassType::fromCode(file_get_contents($pathToTest));
            //letsTakeNamespaceViaregexp
            preg_match('/namespace\s+(.+);/', file_get_contents($pathToTest), $matches);
            $namespace = new PhpNamespace($matches[1]);
            $namespace->add($classType);
        }
        if ($classType->hasMethod('test'.$method)) return;

        $method = $classType->addMethod('test'.ucfirst($method));
        $method->setPublic();
        $method->addBody('throw new \\Exception(\'This Test is not implemented yet\');');

        $this->putNamespaceToFile($namespace, $pathToTest);
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

