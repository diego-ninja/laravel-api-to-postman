<?php

namespace AndreasElia\PostmanGenerator\Enums;

enum CollectionFormat: string
{
    case Postman = 'postman';
    case Insomnia = 'insomnia';

    public static function values(): array
    {
        return [
            self::Postman->value,
            self::Insomnia->value,
        ];
    }
}
