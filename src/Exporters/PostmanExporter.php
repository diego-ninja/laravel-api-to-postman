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
                'name' => $this->filename,
                '_postman_id' => $this->config->get('api-postman.postman_id'),
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
        $preRequestPath = $this->config->get('api-postman.scripts.pre-request');
        $testPath = $this->config->get('api-postman.scripts.test');

        if ($preRequestPath || $testPath) {
            $scripts = [
                'prerequest' => $preRequestPath,
                'test' => $testPath,
            ];

            foreach ($scripts as $type => $path) {
                if (file_exists($path)) {
                    $structure['event'][] = [
                        'listen' => $type,
                        'script' => [
                            'type' => 'text/javascript',
                            'exec' => file_get_contents($path),
                        ],
                    ];
                }
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
        return $this->requests->groupByPath()
            ->map(fn($requests, $group) => [
                'name' => $group,
                'item' => $requests->map(fn($request) => $this->createRequestItem($request))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    protected function createRequestItem(Request $request): array
    {
        return [
            'name' => $this->getRequestName($request),
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

    protected function getRequestName(Request $request): string
    {
        if ($this->config->get('api-postman.structured') && $this->config->get('api-postman.crud_folders')) {
            return $request->method->action() ?? $request->method->value;
        }

        return $request->name;
    }

}
