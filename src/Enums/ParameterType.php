<?php

namespace AndreasElia\PostmanGenerator\Enums;

enum ParameterType: string
{
    case QUERY = 'query';
    case PATH = 'path';
    case HEADER = 'header';
    case TEXT = 'text';
}
