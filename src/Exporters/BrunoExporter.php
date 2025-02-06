<?php

namespace AndreasElia\PostmanGenerator\Exporters;

use AndreasElia\PostmanGenerator\DTO\Request;
use AndreasElia\PostmanGenerator\Enums\Method;
use Illuminate\Support\Str;

final class BrunoExporter extends AbstractExporter
{
    private int $sequence = 1;
    protected function generateStructure(): array
    {
        return [
            'name' => $this->config->get('api-postman.name'),
            'version' => '1',
            'items' => $this->processCollectionItems(),
            'environments' => [
                $this->createEnvironment()
            ],
            'brunoConfig' => [
                'version' => '1',
                'name' => $this->config->get('api-postman.name'),
                'type' => 'collection',
                'ignore' => [
                    'node_modules',
                    '.git'
                ]
            ]
        ];
    }

    protected function createEnvironment(): array
    {
        $variables = [
            [
                'name' => 'base_url',
                'value' => $this->config->get('api-postman.base_url'),
                'type' => 'text',
                'enabled' => true,
                'secret' => false
            ]
        ];

        if ($this->authentication) {
            $variables[] = [
                'name' => 'token',
                'value' => $this->authentication->getToken(),
                'type' => 'text',
                'enabled' => true,
                'secret' => true
            ];
        }

        return [
            'uid' => $this->generateUid(),
            'name' => 'Local',
            'variables' => $variables
        ];
    }

    protected function processCollectionItems(): array
    {
        return [
            [
                'type' => 'folder',
                'name' => $this->config->get('app.name'),
                'items' => $this->config->get('api-postman.structured')
                    ? $this->processStructuredRequests()
                    : $this->processFlatRequests()
            ]
        ];
    }

    protected function processFlatRequests(): array
    {
        return $this->requests
            ->filter(fn(Request $request) => Method::HEAD !== $request->method)
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
        $result = [];

        foreach ($groups as $segment => $data) {
            $currentPath = $parentPath ? $parentPath . '/' . $segment : $segment;

            $folder = [
                'type' => 'folder',
                'name' => $segment,
                'items' => []
            ];

            if (!empty($data['requests'])) {
                foreach ($data['requests'] as $request) {
                    $folder['items'][] = $this->createRequestItem($request, $currentPath);
                }
            }

            if (!empty($data['children'])) {
                $folder['items'] = array_merge(
                    $folder['items'],
                    $this->processNestedGroups($data['children'], $currentPath)
                );
            }

            $result[] = $folder;
        }

        return $result;
    }

    protected function createRequestItem(Request $request, ?string $currentPath = null): array
    {
        $name = $request->getName($this->config->get('api-postman.crud_folders'));

        if ($currentPath && $this->config->get('api-postman.structured')) {
            $name = sprintf('[%s] %s', strtoupper($request->method->value), $name);
        }

        return [
            'uid' => $this->generateUid(),
            'type' => 'http-request',
            'name' => $name,
            'seq' => $this->sequence++,
            'request' => [
                'url' => $this->formatUrl($request),
                'method' => $request->method->value,
                'headers' => $this->formatHeaders($request),
                'params' => $this->formatParams($request),
                'body' => $this->formatBody($request),
                'script' => $this->formatScripts(),
                'vars' => [
                    'req' => null,
                    'res' => null
                ],
                'assertions' => [],
                'tests' => '',
                'docs' => $request->description ?? '',
                'auth' => $this->formatAuthentication()
            ]
        ];
    }

    protected function formatScripts(): array
    {
        $scripts = [
            'req' => $this->getScript('pre-request'),
            'res' => $this->getScript('post-response')
        ];

        return array_filter($scripts);
    }

    protected function formatUrl(Request $request): string
    {
        $url = trim($request->uri, '/');
        return '{{ base_url }}/' . $url;
    }

    protected function formatHeaders(Request $request): array
    {
        return $request->headers->map(function($header) {
            return [
                'uid' => $this->generateUid(),
                'name' => $header->key,
                'value' => $header->value,
                'description' => null,
                'enabled' => true
            ];
        })->values()->all();
    }

    protected function formatParams(Request $request): array
    {
        return $request->parameters
            ->map(function($parameter) {
                return [
                    'uid' => $this->generateUid(),
                    'name' => $parameter->name,
                    'value' => $parameter->value,
                    'description' => $parameter->description,
                    'type' => 'query',
                    'enabled' => !$parameter->disabled
                ];
            })
            ->values()
            ->all();
    }

    protected function formatAuthentication(): array
    {
        if (!$this->authentication) {
            return ['mode' => 'none'];
        }

        $auth = [
            'mode' => $this->authentication->getType()
        ];

        if ($this->authentication->getType() === 'bearer') {
            $auth['bearer'] = ['token' => '{{token}}'];
        } elseif ($this->authentication->getType() === 'basic') {
            $auth['basic'] = [
                'username' => '',
                'password' => '{{token}}'
            ];
        }

        return $auth;
    }

    protected function formatBody(Request $request): array
    {
        $body = [
            'mode' => 'none',
            'json' => null,
            'text' => null,
            'xml' => null,
            'graphql' => null,
            'formUrlEncoded' => [],
            'multipartForm' => []
        ];

        if (!$request->body) {
            return $body;
        }

        if (isset($request->body['urlencoded'])) {
            $body['mode'] = 'formUrlEncoded';
            $body['formUrlEncoded'] = collect($request->body['urlencoded'])
                ->map(function($param) {
                    return [
                        'uid' => $this->generateUid(),
                        'name' => $param['key'],
                        'value' => $param['value'] ?? '',
                        'description' => $param['description'] ?? null,
                        'enabled' => true
                    ];
                })
                ->values()
                ->all();
        }

        return $body;
    }

    protected function getRequestName(Request $request): string
    {
        if ($this->config->get('api-postman.structured') && $this->config->get('api-postman.crud_folders')) {
            return $request->method->action() ?? $request->method->value;
        }

        return $request->name;
    }

    protected function generateUid(): string
    {
        return Str::random(21);
    }
}
