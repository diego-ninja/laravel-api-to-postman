<?php

namespace AndreasElia\PostmanGenerator\Collections;

use AndreasElia\PostmanGenerator\DTO\Header;
use AndreasElia\PostmanGenerator\Enums\ParameterType;
use Illuminate\Support\Collection;

final class HeaderCollection extends Collection
{
    /**
     * @param array<Header> $headers
     */
    public static function from(array $headers): HeaderCollection
    {
        return new self(array_map(fn (array $header) => Header::from($header), $headers));
    }

    public function formatted(): array
    {
        return $this->map(function (Header $header) {
            return [
                'key' => $header->key,
                'value' => $header->value,
                'type' => ParameterType::TEXT
            ];
        })->all();
    }
}
