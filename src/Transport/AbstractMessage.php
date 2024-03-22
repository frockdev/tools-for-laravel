<?php

namespace FrockDev\ToolsForLaravel\Transport;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Support\Transformation\TransformationContextFactory;

abstract class AbstractMessage extends Data
{
    public array $context = [];

    public function __construct()
    {
        $this->except('context');
    }

    protected array $excepted = [];

    public function except(string ...$except): static
    {
        $this->excepted = array_merge($this->excepted, $except);
        return parent::except(...$this->excepted);
    }

    protected array $onlyed = [];

    public function only(string ...$only): static
    {
        $this->onlyed = array_merge($this->onlyed, $only);
        return parent::only(...$this->onlyed);
    }

    public function transform(TransformationContext|TransformationContextFactory|null $transformationContext = null,): array
    {
        $result =  parent::transform($transformationContext);
        parent::except(...$this->excepted);
        parent::only(...$this->onlyed);
        return $result;
    }
}
