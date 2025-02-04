<?php

namespace AndreasElia\PostmanGenerator\DTO;

use AndreasElia\PostmanGenerator\Collections\ParameterCollection;
use AndreasElia\PostmanGenerator\Enums\Method;
use AndreasElia\PostmanGenerator\Formatters\RuleFormatter;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

final readonly class Url implements \JsonSerializable
{
    public function __construct(
        public string $raw,
        public array $host,
        public array $path,
        public array $variable = [],
        public array $query = []
    ) {
    }

    public static function fromRoute(Route $route, Method $method, ParameterCollection $formParameters): Url
    {
        $uri  = Str::of($route->uri())->replaceMatches('/{([[:alnum:]_]+)}/', ':$1');

        $data = [
            'raw' => '{{base_url}}/' . $uri,
            'host' => ['{{base_url}}'],
            'path' => explode('/', trim($uri, '/')),
        ];

        $pathVariables = [];
        preg_match_all('/\{([^}]+)}/', $route->uri(), $matches);
        if (!empty($matches[1])) {
            $pathVariables = array_map(function($param) {
                return [
                    'key' => $param,
                    'value' => ''
                ];
            }, $matches[1]);
        }

        $data['variable'] = $pathVariables;

        if ($method === Method::GET && !$formParameters->isEmpty()) {
            $data['query'] = $formParameters->map(function(Parameter $param) {
                return [
                    'key' => $param->name,
                    'value' => $param->value ?? '',
                    'description' => app(RuleFormatter::class)->format($param->name,  [$param->description]),
                    'disabled' => false
                ];
            })->values()->all();
        }

        return self::from($data);
    }

    public static function from(string|array $data): Url
    {
        if (is_string($data)) {
            return self::from(json_decode($data, true));
        }

        return new self(
            raw: $data['raw'],
            host: $data['host'],
            path: $data['path'],
            variable: $data['variable'] ?? [],
            query: $data['query'] ?? []
        );
    }

    public function array(): array
    {
        return [
            'raw' => $this->raw,
            'host' => $this->host,
            'path' => $this->path,
            'variable' => $this->variable,
            'query' => $this->query
        ];
    }

    public function json(): string
    {
        return json_encode($this->array());
    }

    public function jsonSerialize(): array
    {
        return $this->array();
    }
}
