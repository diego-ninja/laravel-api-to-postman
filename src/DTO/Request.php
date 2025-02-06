<?php

namespace AndreasElia\PostmanGenerator\DTO;

use AndreasElia\PostmanGenerator\Collections\HeaderCollection;
use AndreasElia\PostmanGenerator\Collections\ParameterCollection;
use AndreasElia\PostmanGenerator\Enums\Method;
use JsonSerializable;

final readonly class Request implements JsonSerializable
{
    public function __construct(
        public string $name,
        public Method $method,
        public string $uri,
        public string $description,
        public HeaderCollection $headers,
        public ParameterCollection $parameters,
        public Url $url,
        public ?array $authentication,
        public ?array $body,
        public ?string $group = null,
    ) {}

    public static function from(string|array $data): Request
    {
        if (is_string($data)) {
            return self::from(json_decode($data, true));
        }

        return new self(
            name: $data['name'],
            method: Method::from($data['method']),
            uri: $data['uri'],
            description: $data['description'] ?? null,
            headers: HeaderCollection::from($data['headers']),
            parameters: ParameterCollection::from($data['parameters']),
            url: Url::from($data['url']),
            authentication: $data['authentication'] ?? null,
            body: $data['body'] ?? null,
            group: $data['group'] ?? null,
        );
    }

    public function name(?bool $useCrudFolders): string
    {
        if ($useCrudFolders) {
            return $this->method->action() ?? $this->name;
        }

        return $this->name;
    }

    public function group(): string
    {
        if ($this->method === Method::HEAD) {
            return '';
        }

        return $this->group !== null ? $this->group : explode('/', mb_trim($this->uri, '/'))[0] ?? 'Default';
    }

    public function array(): array
    {
        return [
            'name' => $this->name,
            'method' => $this->method->value,
            'uri' => $this->uri,
            'description' => $this->description,
            'headers' => $this->headers,
            'parameters' => $this->parameters,
            'url' => $this->url->array(),
            'authentication' => $this->authentication,
            'body' => $this->body,
            'group' => $this->group,
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
