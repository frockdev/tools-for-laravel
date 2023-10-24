<?php

namespace FrockDev\ToolsForLaravel\PreInterceptors;

use Attribute;
use FrockDev\ToolsForLaravel\InterceptorInterfaces\PreInterceptorInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateEndpointInput implements PreInterceptorInterface
{
    private array $rules;

    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    public function intercept(array &$ctx, \Google\Protobuf\Internal\Message &$in): void
    {
        if (method_exists($in, 'convertToArray')) {
            $validator = Validator::make($in->convertToArray(), $this->rules);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        }

    }
}
