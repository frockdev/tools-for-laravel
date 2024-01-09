<?php

namespace FrockDev\ToolsForLaravel\PreInterceptors;

use Attribute;
use FrockDev\ToolsForLaravel\InterceptorInterfaces\PreInterceptorInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateInput implements PreInterceptorInterface
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
        } else {
            Log::warning('Input message '.get_class($in).' does not have convertToArray method, skipping validation');
        }

    }
}
