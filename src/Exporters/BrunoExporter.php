<?php

namespace AndreasElia\PostmanGenerator\Exporters;

use AndreasElia\PostmanGenerator\DTO\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

final class BrunoExporter extends AbstractExporter
{
    protected array $brunoRequests = [];

    protected function generateStructure(): array
    {
        $basePath = Storage::path('bruno/' . Str::slug($this->filename));

        $this->setupDirectories($basePath);
        $this->createEnvironments($basePath);
        $this->processRequests($basePath);

        return [
            'name' => $this->filename,
            'version' => '1',
            'Bruno-Export' => true,
            'requests' => $this->brunoRequests,
            'environments' => [
                'base_url' => $this->config->get('api-postman.base_url'),
                'token' => $this->authentication?->getToken(),
            ],
        ];
    }

    protected function setupDirectories(string $basePath): void
    {
        if (!File::exists($basePath)) {
            File::makeDirectory($basePath, 0755, true);
        }

        $envPath = $basePath . '/environments';
        if (!File::exists($envPath)) {
            File::makeDirectory($envPath);
        }
    }

    protected function createEnvironments(string $basePath): void
    {
        $environments = [
            'Local' => [
                'base_url' => $this->config->get('api-postman.base_url'),
            ],
        ];

        if ($this->authentication) {
            $environments['Local']['token'] = $this->authentication->getToken();
        }

        foreach ($environments as $name => $vars) {
            File::put(
                $basePath . '/environments/' . Str::slug($name) . '.env.json',
                json_encode(['variables' => $vars], JSON_PRETTY_PRINT)
            );
        }
    }

    protected function processRequests(string $basePath): void
    {
        if ($this->config->get('api-postman.structured')) {
            $this->processStructuredRequests($basePath);
        } else {
            $this->processFlatRequests($basePath);
        }
    }

    protected function processFlatRequests(string $basePath): void
    {
        $this->requests
            ->filter(fn($request) => $request->method->value !== 'HEAD')
            ->each(function($request) use ($basePath) {
                $this->brunoRequests[] = $this->createRequestData($request);
                $fileName = sprintf(
                    '%s_%s.bru',
                    strtolower($request->method->value),
                    Str::slug($request->name)
                );

                File::put(
                    $basePath . '/' . $fileName,
                    $this->formatRequest($request)
                );
            });
    }

    protected function processStructuredRequests(string $basePath): void
    {
        $requests = $this->requests->filter(fn($request) => $request->method->value !== 'HEAD');

        $basePrefix = '';
        if ($firstRequest = $requests->first()) {
            $segments = array_values(array_filter(explode('/', trim($firstRequest->uri, '/'))));
            $basePrefix = $segments[0] ?? '';
        }

        if ($basePrefix) {
            $basePrefixPath = $basePath . '/' . $basePrefix;
            if (!File::exists($basePrefixPath)) {
                File::makeDirectory($basePrefixPath, 0755, true);
            }
        }

        foreach ($requests as $request) {
            $this->brunoRequests[] = $this->createRequestData($request);

            $segments = array_values(array_filter(explode('/', trim($request->uri, '/'))));

            if (count($segments) <= 2) {
                $this->createRequestFile($basePrefixPath ?? $basePath, $request);
                continue;
            }

            $currentPath = $basePrefixPath ?? $basePath;
            $resourcePath = [];

            for ($i = 1; $i < count($segments); $i++) {
                $segment = $segments[$i];

                if (str_starts_with($segment, '{')) {
                    continue;
                }

                $resourcePath[] = $segment;
                $nextPath = $currentPath . '/' . $segment;

                if (!File::exists($nextPath)) {
                    File::makeDirectory($nextPath, 0755, true);
                }

                $currentPath = $nextPath;
            }

            $this->createRequestFile($currentPath, $request);
        }
    }
    protected function createRequestFile(string $path, Request $request): void
    {
        $action = $this->getRequestAction($request);
        $method = strtolower($request->method->value);

        $fileName = sprintf('%s_%s.bru', $method, $action);

        File::put(
            $path . '/' . $fileName,
            $this->formatRequest($request)
        );
    }

    protected function getRequestAction(Request $request): string
    {
        if ($request->name) {
            $parts = explode('.', $request->name);

            if (count($parts) >= 3) {
                return end($parts);
            }

            $lastPart = end($parts);
            if ($lastPart !== 'index') {
                return $lastPart;
            }
        }

        return $request->method->action() ?? $request->method->value;
    }

    protected function createRequestData(Request $request, ?string $group = null): array
    {
        return [
            'name' => $this->getRequestName($request),
            'method' => $request->method->value,
            'url' => $this->formatUrl($request),
            'description' => $request->description,
            'group' => $group,
            'auth' => $this->formatAuthentication(),
            'headers' => $request->headers->formatted(),
            'body' => $request->body,
        ];
    }

    protected function formatRequest(Request $request): string
    {
        $template = $this->getStubContent('bruno.request.stub');

        return sprintf(
            $template,
            $this->getRequestName($request),
            $this->formatDescription($request),
            strtolower($request->method->value),
            $this->formatUrl($request),
            $this->formatAuthentication(),
            $this->formatHeaders($request),
            $this->formatBody($request)
        );
    }

    protected function getStubContent(string $stubName): string
    {
        $stubPath = __DIR__ . '/../../stubs/' . $stubName;

        if (!File::exists($stubPath)) {
            throw new \RuntimeException(sprintf('Stub file %s does not exist', $stubPath));
        }

        return File::get($stubPath);
    }

    protected function formatDescription(Request $request): string
    {
        if (empty($request->description)) {
            return '';
        }

        return "# " . str_replace("\n", "\n# ", $request->description) . "\n";
    }

    protected function formatUrl(Request $request): string
    {
        $path = trim($request->uri, '/');
        // Keep {param} format for Bruno
        return "/{$path}";
    }

    protected function formatHeaders(Request $request): string
    {
        return $request->headers
            ->map(fn ($header) => "  {$header->key}: {$header->value}")
            ->implode("\n");
    }

    protected function formatAuthentication(): string
    {
        if (!$this->authentication) {
            return 'none';
        }

        $type = $this->authentication->getType();
        return match ($type) {
            'bearer' => 'bearer',
            'basic' => 'basic',
            default => 'none'
        };
    }

    protected function formatBody(Request $request): string
    {
        if (!$request->body) {
            return '';
        }

        $body = "body:form-urlencoded {\n";
        foreach ($request->body['urlencoded'] as $param) {
            $value = $param['value'] ?? '';
            $body .= "  {$param['key']}: {$value}";

            if (!empty($param['description'])) {
                $body .= " # {$param['description']}";
            }

            $body .= "\n";
        }
        $body .= "}\n";

        return $body;
    }

    protected function getRequestName(Request $request): string
    {
        if ($this->config->get('api-postman.structured') && $this->config->get('api-postman.crud_folders')) {
            return $request->method->action() ?? $request->method->value;
        }

        return $request->name;
    }
}
