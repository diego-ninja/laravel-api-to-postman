<?php

namespace AndreasElia\PostmanGenerator\Exporters;

use AndreasElia\PostmanGenerator\DTO\Request;
use AndreasElia\PostmanGenerator\Enums\Method;
use Illuminate\Support\Str;

final class InsomniaExporter extends AbstractExporter
{
    private string $workspaceId;
    protected function generateStructure(): array
    {
        $this->workspaceId = 'wrk_' . Str::uuid()->toString();

        return [
            '_type' => 'export',
            '__export_format' => 4,
            '__export_date' => date('Y-m-d H:i:s'),
            '__export_source' => 'laravel-api-to-insomnia',
            'resources' => $this->generateResources(),
        ];
    }

    protected function generateResources(): array
    {
        $workspace = $this->createWorkspace();
        $environment = $this->createEnvironment();
        $requests = $this->processRequests();

        return array_merge([$workspace, $environment], $requests);
    }

    protected function createWorkspace(): array
    {
        return [
            '_id' => $this->workspaceId,
            '_type' => 'workspace',
            'parentId' => null,
            'name' => $this->config->get('api-postman.name'),
            'description' => $this->config->get('app.description'),
            'scope' => 'collection',
        ];
    }

    protected function createEnvironment(): array
    {
        $data = [
            '_id' => 'env_' . Str::uuid()->toString(),
            '_type' => 'environment',
            'parentId' => $this->workspaceId,
            'name' => 'Base Environment',
            'data' => [
                'base_url' => $this->config->get('api-postman.base_url')
            ]
        ];

        if ($this->authentication) {
            $data['data']['token'] = $this->authentication->getToken();
        }

        return $data;
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
            ->filter(fn(Request $request) => Method::HEAD !== $request->method)
            ->map(fn(Request $request) => $this->createRequestResource($request, $this->workspaceId))
            ->values()
            ->all();
    }

    protected function processStructuredRequests(): array
    {
        $resources = [];
        $grouped = $this->requests
            ->filter(fn(Request $request) => Method::HEAD !== $request->method)
            ->groupByNestedPath();

        $this->processNestedGroups($grouped, $resources);

        return $resources;
    }

    protected function processNestedGroups(array $groups, array &$resources, ?string $parentId = null): void
    {
        $parentId = $parentId ?? $this->workspaceId;

        foreach ($groups as $segment => $data) {
            $folderId = 'fld_' . Str::uuid()->toString();

            // Create folder
            $resources[] = [
                '_id' => $folderId,
                '_type' => 'request_group',
                'parentId' => $parentId,
                'name' => Str::title($segment),
                'description' => '',
                'scope' => 'collection',
            ];

            // Add requests to current folder
            foreach ($data['requests'] as $request) {
                $resources[] = $this->createRequestResource($request, $folderId);
            }

            // Process nested folders
            if (!empty($data['children'])) {
                $this->processNestedGroups($data['children'], $resources, $folderId);
            }
        }
    }

    protected function createRequestResource(Request $request, ?string $parentId = null): array
    {
        $requestResource = [
            '_id' => 'req_' . Str::uuid()->toString(),
            '_type' => 'request',
            'parentId' => $parentId,
            'name' => $request->getName(
                $this->config->get('api-postman.structured') &&
                $this->config->get('api-postman.crud_folders')
            ),
            'description' => $request->description,
            'method' => $request->method->value,
            'url' => $this->formatUrl($request),
            'parameters' => $this->formatParameters($request),
            'headers' => $this->formatHeaders($request),
            'authentication' => $this->formatAuthentication(),
            'body' => $this->formatBody($request),
            'settingStoreCookies' => true,
            'settingSendCookies' => true,
            'settingDisableRenderRequestBody' => false,
            'settingEncodeUrl' => true,
            'settingFollowRedirects' => 'global',
            'settingRebuildPath' => true,
        ];

        if ($this->config->get('api-postman.protocol_profile_behavior.disable_body_pruning')) {
            $requestResource['protocolProfileBehavior'] = [
                'disableBodyPruning' => true,
            ];
        }

        return $requestResource;
    }

    protected function formatUrl(Request $request): string
    {
        $baseUrl = '{{ base_url }}';
        $path = mb_trim($request->uri, '/');

        $path = preg_replace('/\{([^}]+)}/', ':$1', $path);

        return "{$baseUrl}/{$path}";
    }

    protected function formatParameters(Request $request): array
    {
        return $request->parameters
            ->map(fn($parameter) => [
                'name' => $parameter->name,
                'value' => $parameter->value,
                'description' => $parameter->description,
                'disabled' => $parameter->disabled,
            ])
            ->values()
            ->all();
    }

    protected function formatHeaders(Request $request): array
    {
        return $request->headers
            ->map(fn($header) => [
                'name' => $header->key,
                'value' => $header->value,
            ])
            ->values()
            ->all();
    }

    protected function formatAuthentication(): array
    {
        if ( ! $this->authentication) {
            return ['type' => 'none'];
        }

        return [
            'type' => $this->authentication->getType(),
            'token' => '{{ token }}',
            'prefix' => $this->authentication->prefix(),
        ];
    }

    protected function formatBody(Request $request): ?array
    {
        if ( ! $request->body) {
            return null;
        }

        return [
            'mimeType' => 'application/x-www-form-urlencoded',
            'params' => collect($request->body['urlencoded'])
                ->map(fn($param) => [
                    'name' => $param['key'],
                    'value' => $param['value'],
                    'description' => $param['description'] ?? '',
                    'disabled' => false,
                ])
                ->values()
                ->all(),
        ];
    }
}
