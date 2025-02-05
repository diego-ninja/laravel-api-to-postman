<?php

namespace AndreasElia\PostmanGenerator\Tests\Feature;

use AndreasElia\PostmanGenerator\Tests\Fixtures\BrunoCollectionHelpersTrait;
use AndreasElia\PostmanGenerator\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

class ExportBrunoCollectionTest extends TestCase
{
    use BrunoCollectionHelpersTrait;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('api-postman.filename', 'test-api');
        config()->set('api-postman.base_url', 'http://api.test');

        Storage::disk()->deleteDirectory('bruno');
    }

    #[DataProvider('providerFormDataEnabled')]
    public function test_standard_export_works(bool $formDataEnabled): void
    {
        config()->set('api-postman.enable_formdata', $formDataEnabled);

        $this->artisan('export:collection --format=bruno')->assertExitCode(0);

        $basePath = Storage::path('bruno/test-api');

        // Verify directory structure
        $this->assertDirectoryExists($basePath);
        $this->assertDirectoryExists($basePath . '/environments');

        // Verify environment file
        $envContent = json_decode(file_get_contents($basePath . '/environments/local.env.json'), true);
        $this->assertArrayHasKey('variables', $envContent);
        $this->assertEquals('http://api.test', $envContent['variables']['base_url']);

        // Count and verify .bru files
        $requests = $this->countCollectionItems(glob($basePath . '/*.bru'));
        $routes = $this->app['router']->getRoutes();

        $this->assertEquals(count($routes), $requests);
    }

    #[DataProvider('providerFormDataEnabled')]
    public function test_structured_export_works(bool $formDataEnabled): void
    {
        config([
            'api-postman.structured' => true,
            'api-postman.enable_formdata' => $formDataEnabled,
        ]);

        $this->artisan('export:collection --format=bruno')->assertExitCode(0);

        $basePath = Storage::path('bruno/test-api');

        // Verify folder structure exists
        $this->assertDirectoryExists($basePath);

        // Get all directories excluding 'environments'
        $folders = array_filter(glob($basePath . '/*'), 'is_dir');
        $folders = array_filter($folders, fn($folder) => !str_ends_with($folder, 'environments'));

        // Should have at least one folder for routes
        $this->assertNotEmpty($folders);

        // Function to recursively find .bru files
        $findBruFiles = function($dir) use (&$findBruFiles) {
            $files = [];
            $contents = glob($dir . '/*');

            foreach ($contents as $item) {
                if (is_dir($item)) {
                    $files = array_merge($files, $findBruFiles($item));
                } elseif (str_ends_with($item, '.bru')) {
                    $files[] = $item;
                }
            }

            return $files;
        };

        // Count total .bru files in all subfolders
        $totalFiles = 0;
        foreach ($folders as $folder) {
            $bruFiles = $findBruFiles($folder);
            $totalFiles += $this->countCollectionItems($bruFiles);
        }

        $routes = $this->app['router']->getRoutes();
        $this->assertEquals(count($routes), $totalFiles);
    }

    #[DataProvider('providerFormDataEnabled')]
    public function test_bearer_auth_export_works(bool $formDataEnabled): void
    {
        config()->set('api-postman.enable_formdata', $formDataEnabled);

        $this->artisan('export:collection --format=bruno --bearer=1234567890')->assertExitCode(0);

        $basePath = Storage::path('bruno/test-api');

        // Verify token in environment
        $envContent = json_decode(file_get_contents($basePath . '/environments/local.env.json'), true);
        $this->assertEquals('1234567890', $envContent['variables']['token']);

        // Verify auth in .bru files
        $bruFiles = glob($basePath . '/*.bru');
        foreach ($bruFiles as $file) {
            $content = file_get_contents($file);
            if (preg_match('/auth: (.+)/', $content, $matches)) {
                $this->assertEquals('bearer', trim($matches[1]));
            }
        }
    }

    #[DataProvider('providerFormDataEnabled')]
    public function test_basic_auth_export_works(bool $formDataEnabled): void
    {
        config()->set('api-postman.enable_formdata', $formDataEnabled);

        $this->artisan('export:collection --format=bruno --basic=username:password1234')->assertExitCode(0);

        $basePath = Storage::path('bruno/test-api');

        // Verify credentials in environment
        $envContent = json_decode(file_get_contents($basePath . '/environments/local.env.json'), true);
        $this->assertEquals('username:password1234', $envContent['variables']['token']);

        // Verify auth in .bru files
        $bruFiles = glob($basePath . '/*.bru');
        foreach ($bruFiles as $file) {
            $content = file_get_contents($file);
            if (preg_match('/auth: (.+)/', $content, $matches)) {
                $this->assertEquals('basic', trim($matches[1]));
            }
        }
    }

    public function test_request_format_is_correct(): void
    {
        $this->artisan('export:collection --format=bruno')->assertExitCode(0);

        $basePath = Storage::path('bruno/test-api');

        $bruFiles = glob($basePath . '/*.bru');
        $this->assertNotEmpty($bruFiles);

        $content = file_get_contents($bruFiles[0]);

        // Check basic structure
        $this->assertStringContainsString('meta {', $content);
        $this->assertStringContainsString('type: http', $content);

        // Check URL format
        $this->assertMatchesRegularExpression('/url: \{\{ base_url }}\/.*/', $content);

        // Check headers section exists
        $this->assertStringContainsString('headers {', $content);
    }

    public static function providerFormDataEnabled(): array
    {
        return [
            [false],
            [true],
        ];
    }
}
