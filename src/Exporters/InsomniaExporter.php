<?php

namespace AndreasElia\PostmanGenerator\Exporters;

use AndreasElia\PostmanGenerator\DTO\Request;
use Illuminate\Support\Str;

final class InsomniaExporter extends AbstractExporter
{
    protected function generateStructure(): array
    {
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
            '_id' => 'wrk_' . Str::uuid()->toString(),
            '_type' => 'workspace',
            'parentId' => null,
            'name' => $this->filename,
            'description' => $this->config->get('app.description'),
            'scope' => 'collection',
        ];
    }

    protected function createEnvironment(): array
    {
        $environment = [
            '_id' => 'env_' . Str::uuid()->toString(),
            '_type' => 'environment',
            'parentId' => 'wrk_' . Str::uuid()->toString(),
            'name' => 'Base Environment',
            'data' => [
                [
                    'name' => 'base_url',
                    'value' => $this->config->get('api-postman.base_url'),
                ],
            ],
        ];

        if ($this->authentication) {
            $environment['data'][] = [
                'name' => 'token',
                'value' => $this->authentication->getToken(),
            ];
        }

        return $environment;
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
            ->filter(fn(Request $request) => 'HEAD' !== $request->method->value)
            ->map(fn($request) => $this->createRequestResource($request))
            ->values()
            ->all();
    }

    protected function processStructuredRequests(): array
    {
        $resources = [];
        $groups = $this->requests
            ->filter(fn(Request $request) => 'HEAD' !== $request->method->value)
            ->groupByPath();

        foreach ($groups as $groupName => $groupRequests) {
            $folderId = 'fld_' . Str::uuid()->toString();

            // Add folder
            $resources[] = [
                '_id' => $folderId,
                '_type' => 'request_group',
                'parentId' => 'wrk_' . Str::uuid()->toString(),
                'name' => $groupName,
                'description' => '',
                'scope' => 'collection',
                'preRequestScript' => $this->getScript('pre-request'),
                'afterResponseScript' => $this->getScript('post-response'),
            ];

            // Add requests to folder
            foreach ($groupRequests as $request) {
                $resources[] = $this->createRequestResource($request, $folderId);
            }
        }

        return $resources;
    }

    protected function createRequestResource(Request $request, ?string $parentId = null): array
    {
        $requestResource = [
            '_id' => 'req_' . Str::uuid()->toString(),
            '_type' => 'request',
            'parentId' => $parentId ?? 'wrk_' . Str::uuid()->toString(),
            'name' => $this->getRequestName($request),
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

    protected function getRequestName(Request $request): string
    {
        if ($this->config->get('api-postman.structured') && $this->config->get('api-postman.crud_folders')) {
            return $request->method->action() ?? $request->method->value;
        }

        return $request->name;
    }
}
