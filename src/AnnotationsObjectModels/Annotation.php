<?php

namespace FrockDev\ToolsForLaravel\AnnotationsObjectModels;

class Annotation
{
    private string $className;

    private array $arguments = [];

    public function __construct(
        string $className,
        array $arguments
    ) {
        $this->className = $className;
        foreach ($arguments as $name=>$value) {
            $this->arguments[] = new AnnotationArgument($name, $value);
        }
        $this->arguments = $arguments;
    }

    /**
     * @return array|AnnotationArgument[]
     */
    public function getArguments(): array {
        return $this->arguments;
    }

    public function getAnnotationClassName(): string
    {
        return $this->className;
    }
    static function __set_state(array $array) {
        return new self(...$array);
    }

}
