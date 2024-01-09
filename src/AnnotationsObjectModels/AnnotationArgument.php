<?php

namespace FrockDev\ToolsForLaravel\AnnotationsObjectModels;

class AnnotationArgument
{
    private string $name;

    private mixed $value;

    public function __construct(
        string $name,
        $value
    ) {
        $this->name = $name;
        $this->value = $value;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getValue() {
        return $this->value;
    }

    static function __set_state(array $array) {
        return new self(...$array);
    }
}
