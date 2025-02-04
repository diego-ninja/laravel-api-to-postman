<?php

namespace AndreasElia\PostmanGenerator\Tests\Fixtures;

trait InsomniaCollectionHelpersTrait
{
    private function retrieveRoutes(array $route): int
    {
        // Skip patch routes
        if (isset($route['method']) && $route['method'] === 'PATCH') {
            return 0;
        }

        // For Insomnia format
        if (isset($route['_type']) && $route['_type'] === 'request') {
            return 1;
        }

        return 0;
    }

    private function countCollectionItems(array $collectionItems): int
    {
        // For Insomnia format
        return collect($collectionItems)
            ->filter(fn($item) => $item['_type'] === 'request')
            ->filter(fn($item) => !in_array($item['method'], ['HEAD', 'PATCH']))
            ->count();
    }
}
