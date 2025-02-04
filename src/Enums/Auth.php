<?php

namespace AndreasElia\PostmanGenerator\Enums;

enum Auth: string
{
    case Basic = 'basic';
    case Bearer = 'bearer';

    public function prefix(): string
    {
        return ucfirst($this->value);
    }
}
