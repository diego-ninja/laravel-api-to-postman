<?php

namespace AndreasElia\PostmanGenerator\Collections;

use AndreasElia\PostmanGenerator\DTO\Parameter;
use AndreasElia\PostmanGenerator\Enums\ParameterType;
use Illuminate\Support\Collection;

final class ParameterCollection extends Collection
{
    /**
     * @param array<Parameter> $parameters
     */
    public static function from(array $parameters): ParameterCollection
    {
        return new self(array_map(fn(array|Parameter $parameter) => Parameter::from($parameter), $parameters));
    }

    public function byType(ParameterType $type): ParameterCollection
    {
        return $this->filter(fn(Parameter $parameter) => $parameter->type === $type);
    }
}
