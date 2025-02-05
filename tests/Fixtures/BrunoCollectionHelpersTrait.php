<?php

namespace AndreasElia\PostmanGenerator\Tests\Fixtures;

trait BrunoCollectionHelpersTrait
{
    private function retrieveRoutes(array $route): int
    {
        if ($route['type'] === 'folder') {
            $sum = 0;
            foreach ($route['items'] as $item) {
                $sum += $this->retrieveRoutes($item);
            }
            return $sum;
        }

        if (isset($route['request']['method']) && $route['request']['method'] === 'PATCH') {
            return 0;
        }

        // For Bruno JSON format
        if ($route['type'] === 'http-request') {
            return 1;
        }

        return 0;
    }

    private function countCollectionItems(array $collectionItems): int
    {
        $sum = 0;
        foreach ($collectionItems as $item) {
            $sum += $this->retrieveRoutes($item);
        }

        return $sum;
    }
}
