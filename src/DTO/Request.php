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
        public ?array $body
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
            body: $data['body'] ?? null
        );
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
            'body' => $this->body
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
