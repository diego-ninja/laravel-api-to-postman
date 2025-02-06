<?php

namespace AndreasElia\PostmanGenerator\Exporters;

use AndreasElia\PostmanGenerator\DTO\Request;

final class PostmanExporter extends AbstractExporter
{
    protected function generateStructure(): array
    {
        $structure = [
            'variable' => [
                [
                    'key' => 'base_url',
                    'value' => $this->config->get('api-postman.base_url'),
                ],
            ],
            'info' => [
                'name' => $this->config->get('api-postman.name'),
                'description' => $this->config->get('app.description'),
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'item' => $this->processRequests(),
            'event' => [],
        ];

        $this->processAuthentication($structure);
        $this->processEvents($structure);

        return $structure;
    }

    protected function processAuthentication(array &$structure): void
    {
        if ($this->authentication) {
            $structure['variable'][] = [
                'key' => 'token',
                'value' => $this->authentication->getToken(),
            ];

            $structure['auth'] = [
                'type' => $this->authentication->getType(),
                $this->authentication->getType() => [
                    'token' => '{{token}}',
                ],
            ];
        }
    }

    protected function processEvents(array &$structure): void
    {
        $preRequestScript = $this->getScript('pre-request');
        $testScript = $this->getScript('test');

        if ($preRequestScript || $testScript) {
            $scripts = [
                'prerequest' => $preRequestScript,
                'test' => $testScript,
            ];

            foreach ($scripts as $type => $script) {
                $structure['event'][] = [
                    'listen' => $type,
                    'script' => [
                        'type' => 'text/javascript',
                        'exec' => $script,
                    ],
                ];
            }
        }
    }

    protected function processRequests(): array
    {
        return $this->config->get('api-postman.structured')
            ? $this->processStructuredRequests()
            : $this->processFlatRequests();
    }
    protected function processFlatRequests(): array
    {
        return $this->requests
            ->map(fn($request) => $this->createRequestItem($request))
            ->values()
            ->all();
    }

    protected function processStructuredRequests(): array
    {
        return $this->processNestedGroups($this->requests->groupByNestedPath());
    }

    protected function processNestedGroups(array $groups, string $parentPath = ''): array
    {
        $items = [];

        foreach ($groups as $segment => $data) {
            $currentPath = $parentPath . ($parentPath ? '/' : '') . $segment;

            $folder = [
                'name' => $segment,
                'item' => []
            ];

            // Add requests to current folder
            foreach ($data['requests'] as $request) {
                $folder['item'][] = $this->createRequestItem($request);
            }

            // Process nested folders
            if (!empty($data['children'])) {
                $folder['item'] = array_merge(
                    $folder['item'],
                    $this->processNestedGroups($data['children'], $currentPath)
                );
            }

            $items[] = $folder;
        }

        return $items;
    }

    protected function createRequestItem(Request $request): array
    {
        return [
            'name' => $request->name(
                $this->config->get('api-postman.structured') &&
                $this->config->get('api-postman.crud_folders')
            ),
            'request' => [
                'method' => $request->method,
                'header' => $request->headers->formatted(),
                'url' => $request->url,
                'description' => $request->description,
                'body' => $request->body,
            ],
            'response' => [],
        ];
    }
}
