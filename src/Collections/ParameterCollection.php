<?php

namespace AndreasElia\PostmanGenerator\Collections;

use AndreasElia\PostmanGenerator\Attributes\Request;
use AndreasElia\PostmanGenerator\DTO\Parameter;
use AndreasElia\PostmanGenerator\Enums\ParameterType;
use AndreasElia\PostmanGenerator\Formatters\RuleFormatter;
use AndreasElia\PostmanGenerator\Processors\FormDataProcessor;
use Illuminate\Routing\Route;
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

    public function fromRoute(Route $route): self
    {
        preg_match_all('/\{([^}]+)}/', $route->uri(), $matches);
        foreach ($matches[1] as $param) {
            $this->add(new Parameter(
                name: $param,
                value: '',
                description: '',
                type: ParameterType::PATH,
            ));
        }

        return $this;
    }

    public function fromAttribute(?Request $request = null): self
    {
        if ($request?->params) {
            foreach ($request->params as $name => $description) {
                $this->add(new Parameter(
                    name: $name,
                    value: '',
                    description: $description,
                    type: ParameterType::QUERY
                ));
            }
        }

        return $this;
    }

    public function fromFormRequest(mixed $reflectionMethod, array $formdata = []): self
    {
        if (!$reflectionMethod) {
            return $this;
        }

        $formParameters = (new FormDataProcessor)->process($reflectionMethod);

        return $this->merge(
            $formParameters->map(fn(array $param) => new Parameter(
                name: $param['name'],
                value: $formdata[$param['name']] ?? '',
                description: app(RuleFormatter::class)->format($param['name'], $param['description']),
                type: ParameterType::QUERY,
            ))
        );
    }
}
