<?php

namespace AndreasElia\PostmanGenerator\Processors;

use AndreasElia\PostmanGenerator\Collections\HeaderCollection;
use AndreasElia\PostmanGenerator\Collections\ParameterCollection;
use AndreasElia\PostmanGenerator\Collections\RequestCollection;
use AndreasElia\PostmanGenerator\Concerns\HasAuthentication;
use AndreasElia\PostmanGenerator\DTO\Parameter;
use AndreasElia\PostmanGenerator\DTO\Request;
use AndreasElia\PostmanGenerator\DTO\Url;
use AndreasElia\PostmanGenerator\Enums\Method;
use AndreasElia\PostmanGenerator\Enums\ParameterType;
use AndreasElia\PostmanGenerator\Formatters\RuleFormatter;
use Closure;
use Illuminate\Config\Repository;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Validation\Rule;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;

final class RouteProcessor
{
    use HasAuthentication;

    public function __construct(
        private readonly Router $router,
        private readonly Repository $config,
    ) {
        $this->resolveAuth();
    }

    /**
     * @throws ReflectionException
     */
    public function process(): RequestCollection
    {
        $routes = collect($this->router->getRoutes());
        $collection = new RequestCollection();

        foreach ($routes as $route) {
            $this->processRoute($route, $collection);
        }

        return $collection;
    }

    /**
     * @throws ReflectionException
     */
    protected function processRoute(Route $route, RequestCollection $collection): void
    {
        $methods = array_filter(
            array_map(fn($value) => Method::tryFrom(mb_strtoupper($value)), $route->methods()),
            fn($method) => Method::HEAD !== $method,
        );

        $middlewares = $route->gatherMiddleware();

        foreach ($methods as $method) {
            if ( ! $this->shouldProcessRoute($middlewares)) {
                continue;
            }

            $request = new Request(
                name: $route->getName() ?: $route->uri(),
                method: $method,
                uri: $route->uri(),
                description: $this->getDescription($route),
                headers: $this->getHeaders(),
                parameters: $this->getParameters($route),
                url: Url::fromRoute(
                    route: $route,
                    method: $method,
                    formParameters: $this->getParameters($route),
                ),
                authentication: $this->getAuthenticationInfo($middlewares),
                body: Method::GET === $method ? null : $this->getBody($route),
            );

            $collection->add($request);
        }
    }

    /**
     * @throws ReflectionException
     */
    protected function getBody(Route $route): ?array
    {
        $reflectionMethod = $this->getReflectionMethod($route->getAction());
        if ( ! $reflectionMethod || ! $this->config->get('api-postman.enable_formdata')) {
            return null;
        }

        $formParameters = (new FormDataProcessor())->process($reflectionMethod);
        if ($formParameters->isEmpty()) {
            return null;
        }

        return [
            'mode' => 'urlencoded',
            'urlencoded' => $formParameters->map(fn($param) => [
                'key' => $param['name'],
                'value' => $this->config->get('api-postman.formdata')[$param['name']] ?? '',
                'type' => ParameterType::TEXT->value,
                'description' => app(RuleFormatter::class)->format($param['name'], $param['description']),
            ])->values()->all(),
        ];
    }

    /**
     * @throws ReflectionException
     */
    protected function getDescription(Route $route): string
    {
        if ( ! $this->config->get('api-postman.include_doc_comments')) {
            return '';
        }

        $reflectionMethod = $this->getReflectionMethod($route->getAction());
        if ( ! $reflectionMethod) {
            return '';
        }

        return (new DocBlockProcessor())($reflectionMethod);
    }

    protected function shouldProcessRoute(array $middlewares): bool
    {
        foreach ($middlewares as $middleware) {
            if (in_array($middleware, $this->config->get('api-postman.include_middleware'))) {
                return true;
            }
        }
        return false;
    }

    /**
     * @throws ReflectionException
     */
    protected function getParameters(Route $route): ParameterCollection
    {
        $parameters = new ParameterCollection();
        preg_match_all('/\{([^}]+)}/', $route->uri(), $matches);

        foreach ($matches[1] as $param) {
            $parameters->add(new Parameter(
                name: $param,
                value: '',
                description: '',
                type: ParameterType::PATH,
            ));
        }

        $reflectionMethod = $this->getReflectionMethod($route->getAction());
        if ($reflectionMethod && $this->config->get('api-postman.enable_formdata') && 'GET' === $route->methods()[0]) {
            $formParameters = (new FormDataProcessor())->process($reflectionMethod);
            $parameters = $parameters->merge(
                $formParameters->map(fn(array $param) => new Parameter(
                    name: $param['name'],
                    value: $this->config->get('api-postman.formdata')[$param['name']] ?? '',
                    description: $this->formatRuleDescription($param['name'], $param['description']),
                    type: ParameterType::QUERY,
                )),
            );
        }

        return $parameters;
    }

    protected function formatRuleDescription(string $fieldName, string|array|Rule $rules): string
    {
        if ( ! $this->config->get('api-postman.print_rules')) {
            return '';
        }

        if (is_string($rules)) {
            return $rules;
        }

        if (is_array($rules)) {
            return $this->config['rules_to_human_readable']
                ? $this->parseRulesIntoHumanReadable($fieldName, $rules)
                : implode(', ', $rules);
        }

        if (is_object($rules)) {
            return $this->safelyStringifyClassBasedRule($rules);
        }

        return '';
    }

    protected function parseRulesIntoHumanReadable($attribute, $rules): string
    {
        if (is_object($rules)) {
            return $this->safelyStringifyClassBasedRule($rules);
        }

        if (is_array($rules)) {
            $messages = [];
            foreach ($rules as $rule) {
                if (is_string($rule)) {
                    $messages[] = "The {$attribute} field " . $this->humanizeRule($rule);
                } elseif (is_object($rule)) {
                    $messages[] = $this->safelyStringifyClassBasedRule($rule);
                }
            }
            return implode(', ', array_filter($messages));
        }

        return '';
    }

    protected function humanizeRule(string $rule): string
    {
        $parts = explode(':', $rule);
        $ruleName = $parts[0];

        return match ($ruleName) {
            'required' => 'is required',
            'integer' => 'must be an integer',
            'string' => 'must be a string',
            'max' => "must not be greater than {$parts[1]}",
            'min' => "must be at least {$parts[1]}",
            'sometimes' => '(Optional)',
            'nullable' => '(Nullable)',
            default => "must satisfy rule: {$rule}",
        };
    }

    protected function safelyStringifyClassBasedRule($rule): string
    {
        if ( ! is_object($rule) || ! method_exists($rule, '__toString')) {
            return '';
        }

        return (string) $rule;
    }

    protected function getHeaders(): HeaderCollection
    {
        return HeaderCollection::from($this->config->get('api-postman.headers'));
    }

    protected function getAuthenticationInfo(array $middlewares): ?array
    {
        if (in_array($this->config->get('api-postman.auth_middleware'), $middlewares)) {
            $config = $this->config->get('api-postman.authentication');
            return [
                'type' => $config['method'],
                'token' => $config['token'] ?? '{{token}}',
            ];
        }
        return null;
    }


    /**
     * @throws ReflectionException
     */
    private function getReflectionMethod(array $action): ?object
    {
        if ($this->containsSerializedClosure($action)) {
            $action['uses'] = unserialize($action['uses'])->getClosure();
        }

        if ($action['uses'] instanceof Closure) {
            return new ReflectionFunction($action['uses']);
        }

        if ( ! is_string($action['uses'])) {
            return null;
        }

        $routeData = explode('@', $action['uses']);
        if (2 !== count($routeData)) {
            return null;
        }

        $reflection = new ReflectionClass($routeData[0]);
        if ( ! $reflection->hasMethod($routeData[1])) {
            return null;
        }

        return $reflection->getMethod($routeData[1]);
    }

    private function containsSerializedClosure(array $action): bool
    {
        if ( ! is_string($action['uses'])) {
            return false;
        }

        $needles = [
            'C:32:"Opis\\Closure\\SerializableClosure',
            'O:47:"Laravel\\SerializableClosure\\SerializableClosure',
            'O:55:"Laravel\\SerializableClosure\\UnsignedSerializableClosure',
        ];

        foreach ($needles as $needle) {
            if (str_starts_with($action['uses'], $needle)) {
                return true;
            }
        }

        return false;
    }
}
