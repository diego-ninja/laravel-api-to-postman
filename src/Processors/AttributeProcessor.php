<?php

namespace AndreasElia\PostmanGenerator\Processors;

use AndreasElia\PostmanGenerator\Attributes\Collection;
use AndreasElia\PostmanGenerator\Attributes\Request;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

final readonly class AttributeProcessor
{
    /**
     * @throws ReflectionException
     */
    public function getCollectionAttribute(string $className): ?Collection
    {
        $reflector = new ReflectionClass($className);
        $attributes = $reflector->getAttributes(Collection::class);

        if (empty($attributes)) {
            return null;
        }

        /** @var Collection $collection */
        return $attributes[0]->newInstance();
    }

    public function getRequestAttribute(ReflectionMethod $method): ?Request
    {
        $attributes = $method->getAttributes(Request::class);

        if (empty($attributes)) {
            return null;
        }

        /** @var Request $request */
        return $attributes[0]->newInstance();
    }
}
