<?php

namespace AndreasElia\PostmanGenerator\Collections;

use AndreasElia\PostmanGenerator\DTO\Request;
use AndreasElia\PostmanGenerator\Enums\Method;
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

    public function groupByNestedPath(): array
    {
        $grouped = [];

        foreach ($this as $request) {
            if ($request->method === Method::HEAD) {
                continue;
            }

            $path = $this->getRequestPath($request);
            $current = &$grouped;

            for ($i = 0; $i < count($path) - 1; $i++) {
                $segment = $path[$i];
                if (!isset($current[$segment])) {
                    $current[$segment] = ['requests' => [], 'children' => []];
                }
                $current = &$current[$segment]['children'];
            }

            $lastSegment = end($path);
            if (!isset($current[$lastSegment])) {
                $current[$lastSegment] = ['requests' => [], 'children' => []];
            }
            $current[$lastSegment]['requests'][] = $request;
        }

        return $grouped;
    }

    private function getRequestPath(Request $request): array
    {
        $segments = array_values(array_filter(explode('/', trim($request->uri, '/'))));
        $path = [];

        foreach ($segments as $segment) {
            if (str_starts_with($segment, ':') || str_starts_with($segment, '{')) {
                continue;
            }
            $path[] = $segment;
        }

        return $path;
    }
}
