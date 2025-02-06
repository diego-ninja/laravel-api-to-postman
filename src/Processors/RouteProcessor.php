<?php

namespace AndreasElia\PostmanGenerator\Processors;
use AndreasElia\PostmanGenerator\Attributes\Request as RequestAttribute;
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
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;

final class RouteProcessor
{
    use HasAuthentication;

    public function __construct(
        private readonly Router $router,
        private readonly Repository $config,
        private readonly AttributeProcessor $attributeProcessor,
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
            fn(Method $method) => Method::HEAD !== $method,
        );

        $middlewares = $route->gatherMiddleware();

        // Get attributes if available
        $reflectionMethod = $this->getReflectionMethod($route->getAction());
        $requestAttributes = $reflectionMethod ? $this->attributeProcessor->getRequestAttribute($reflectionMethod) : null;
        $collectionAttributes = $reflectionMethod ? $this->attributeProcessor->getCollectionAttribute($reflectionMethod->getDeclaringClass()->getName()) : null;

        foreach ($methods as $method) {
            if ( ! $this->shouldProcessRoute($middlewares)) {
                continue;
            }

            $request = new Request(
                name: $requestAttributes?->name ?? $route->getName() ?: $route->uri(),
                method: $method,
                uri: $route->uri(),
                description: $requestAttributes?->description ?? $this->getDescription($route),
                headers: $this->getHeaders($requestAttributes),
                parameters: $this->getParameters($route),
                url: Url::fromRoute(
                    route: $route,
                    method: $method,
                    formParameters: $this->getParameters($route),
                ),
                authentication: $this->getAuthenticationInfo($middlewares),
                body: Method::GET === $method ? null : $this->getBody($route),
                group: $requestAttributes?->group ?? $collectionAttributes?->group ?? null,
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
    protected function getParameters(Route $route, ?RequestAttribute $request = null): ParameterCollection
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
                    description: app(RuleFormatter::class)->format($param['name'], $param['description']),
                    type: ParameterType::QUERY,
                )),
            );
        }

        return $parameters;
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

    protected function getHeaders(?RequestAttribute $request = null): HeaderCollection
    {
        $configHeaders = $this->config->get('api-postman.headers', []);

        if (empty($request?->headers)) {
            return HeaderCollection::from($configHeaders);
        }

        $mergedHeaders = collect($configHeaders)
            ->merge($request->headers)
            ->map(function($header, $key) {
                if (is_string($key)) {
                    return ['key' => $key, 'value' => $header];
                }
                return $header;
            })
            ->values()
            ->all();

        return HeaderCollection::from($mergedHeaders);
    }
}
