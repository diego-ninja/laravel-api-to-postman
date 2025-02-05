<?php

namespace AndreasElia\PostmanGenerator\Tests\Fixtures;

trait BrunoCollectionHelpersTrait
{
    private function retrieveRoutes(string $bruFile): int
    {
        $content = file_get_contents($bruFile);

        // Skip if not a valid .bru file
        if (!str_contains($content, 'meta {')) {
            return 0;
        }

        // Skip HEAD routes
        if (preg_match('/patch\s*{/', $content)) {
            return 0;
        }

        return 1;
    }

    private function countCollectionItems(array $bruFiles): int
    {
        $sum = 0;
        foreach ($bruFiles as $file) {
            $sum += $this->retrieveRoutes($file);
        }
        return $sum;
    }
}
