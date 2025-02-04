<?php

namespace AndreasElia\PostmanGenerator\DTO;

final readonly class Header
{
    public function __construct(
        public string $key,
        public string $value
    ) {}

    public function array(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value
        ];
    }

    public static function from(string|array $headers): Header
    {
        if (is_string($headers)) {
            return self::from(json_decode($headers, true));
        }

        return new self(
            key: $headers['key'],
            value: $headers['value']
        );
    }
}
