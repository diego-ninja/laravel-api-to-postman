<?php

namespace AndreasElia\PostmanGenerator\Enums;

enum Format: string
{
    case Postman = 'postman';
    case Insomnia = 'insomnia';
    case Bruno = 'bruno';

    public static function values(): array
    {
        return [
            self::Postman->value,
            self::Insomnia->value,
            self::Bruno->value,
        ];
    }
}
