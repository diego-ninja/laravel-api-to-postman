<?php

namespace AndreasElia\PostmanGenerator\Collections;

use AndreasElia\PostmanGenerator\DTO\Request;
use Illuminate\Support\Collection;

final class RequestCollection extends Collection
{
    public static function from(array $requests): RequestCollection
    {
        return new self(array_map(fn(array $request) => Request::from($request), $requests));
    }

    public function groupByPath(): Collection
    {
        return $this->groupBy(fn(Request $request) => explode('/', mb_trim($request->uri, '/'))[0] ?? '');
    }
}
