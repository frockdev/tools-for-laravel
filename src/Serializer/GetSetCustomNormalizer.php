<?php

namespace FrockDev\ToolsForLaravel\Serializer;

use Symfony\Component\Serializer\Annotation\Ignore as LegacyIgnore;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

class GetSetCustomNormalizer extends AbstractObjectNormalizer
{
    private static array $setterAccessibleCache = [];

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => __CLASS__ === static::class || $this->hasCacheableSupportsMethod()];
    }

    /**
     * @param array $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null /* , array $context = [] */): bool
    {
        return parent::supportsNormalization($data, $format) && $this->supports($data::class);
    }

    /**
     * @param array $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null /* , array $context = [] */): bool
    {
        return parent::supportsDenormalization($data, $type, $format) && $this->supports($type);
    }

    /**
     * @deprecated since Symfony 6.3, use "getSupportedTypes()" instead
     */
    public function hasCacheableSupportsMethod(): bool
    {
        trigger_deprecation('symfony/serializer', '6.3', 'The "%s()" method is deprecated, implement "%s::getSupportedTypes()" instead.', __METHOD__, get_debug_type($this));

        return __CLASS__ === static::class;
    }

    /**
     * Checks if the given class has any getter method.
     */
    private function supports(string $class): bool
    {
        if ($this->classDiscriminatorResolver?->getMappingForClass($class)) {
            return true;
        }

        $class = new \ReflectionClass($class);
        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            if ($this->isGetMethod($method)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a method's name matches /^(get|is|has).+$/ and can be called non-statically without parameters.
     */
    private function isGetMethod(\ReflectionMethod $method): bool
    {
        return !$method->isStatic()
            && !($method->getAttributes(Ignore::class) || $method->getAttributes(LegacyIgnore::class))
            && !$method->getNumberOfRequiredParameters()
            && ((2 < ($methodLength = \strlen($method->name)) && str_starts_with($method->name, 'is'))
                || (3 < $methodLength && (str_starts_with($method->name, 'has') || str_starts_with($method->name, 'get')))
            );
    }

    protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
    {
        $reflectionObject = new \ReflectionObject($object);
        $reflectionMethods = $reflectionObject->getMethods(\ReflectionMethod::IS_PUBLIC);

        $attributes = [];
        foreach ($reflectionMethods as $method) {
            if (!$this->isGetMethod($method)) {
                continue;
            }

            $attributeName = lcfirst(substr($method->name, str_starts_with($method->name, 'is') ? 2 : 3));
            if (!property_exists($object, $attributeName)) {
                $attributeName = ucfirst($attributeName);
                if (!property_exists($object,$attributeName)) {
                    throw new \Exception('Property '.$attributeName.' not found in object '.get_class($object));
                }
            }

            if ($this->isAllowedAttribute($object, $attributeName, $format, $context)) {
                $attributes[] = $attributeName;
            }
        }

        return $attributes;
    }

    protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
    {
        $ucfirsted = ucfirst($attribute);

        $getter = 'get'.$ucfirsted;
        if (method_exists($object, $getter) && \is_callable([$object, $getter])) {
            return $object->$getter();
        }

        $isser = 'is'.$ucfirsted;
        if (method_exists($object, $isser) && \is_callable([$object, $isser])) {
            return $object->$isser();
        }

        $haser = 'has'.$ucfirsted;
        if (method_exists($object, $haser) && \is_callable([$object, $haser])) {
            return $object->$haser();
        }

        return null;
    }

    /**
     * @return void
     */
    protected function setAttributeValue(object $object, string $attribute, mixed $value, ?string $format = null, array $context = [])
    {
        $setter = 'set'.ucfirst($attribute);
        $key = $object::class.':'.$setter;

        if (!isset(self::$setterAccessibleCache[$key])) {
            self::$setterAccessibleCache[$key] = method_exists($object, $setter) && \is_callable([$object, $setter]) && !(new \ReflectionMethod($object, $setter))->isStatic();
        }

        if (self::$setterAccessibleCache[$key]) {
            $object->$setter($value);
        }
    }
}
