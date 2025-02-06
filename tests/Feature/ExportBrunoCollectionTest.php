<?php

namespace AndreasElia\PostmanGenerator\Tests\Feature;

use AndreasElia\PostmanGenerator\Tests\Fixtures\BrunoCollectionHelpersTrait;
use AndreasElia\PostmanGenerator\Tests\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

class ExportBrunoCollectionTest extends TestCase
{
    use BrunoCollectionHelpersTrait;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('api-postman.filename', 'test.json');
        config()->set('api-postman.base_url', 'http://api.test');

        Storage::disk()->deleteDirectory('bruno');
    }

    #[DataProvider('providerFormDataEnabled')]
    public function test_standard_export_works(bool $formDataEnabled): void
    {
        config()->set('api-postman.enable_formdata', $formDataEnabled);

        $this->artisan('export:collection --format=bruno')->assertExitCode(0);

        $collection = json_decode(Storage::get('bruno/' . config('api-postman.filename')), true);

        // Verify basic structure
        $this->assertEquals('Laravel API Collection', $collection['name']);
        $this->assertEquals('1', $collection['version']);
        $this->assertArrayHasKey('items', $collection);
        $this->assertArrayHasKey('environments', $collection);
        $this->assertArrayHasKey('brunoConfig', $collection);

        // Verify main folder
        $mainFolder = Arr::first($collection['items']);
        $this->assertEquals('folder', $mainFolder['type']);
        $this->assertEquals('Laravel', $mainFolder['name']);
        $this->assertArrayHasKey('items', $mainFolder);

        // Verify requests
        $requests = $mainFolder['items'];
        $routes = $this->app['router']->getRoutes();

        $this->assertEquals(count($routes), $this->countCollectionItems($requests));

        // Verify request structure for first request
        $firstRequest = Arr::first($requests);
        $this->assertEquals('http-request', $firstRequest['type']);
        $this->assertEquals(1, $firstRequest['seq']);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{21}$/', $firstRequest['uid']);
        $this->assertArrayHasKey('request', $firstRequest);

        $requestDetails = $firstRequest['request'];
        $this->assertArrayHasKey('url', $requestDetails);
        $this->assertArrayHasKey('method', $requestDetails);
        $this->assertArrayHasKey('headers', $requestDetails);
        $this->assertArrayHasKey('params', $requestDetails);
        $this->assertArrayHasKey('body', $requestDetails);
        $this->assertArrayHasKey('script', $requestDetails);
    }

    public function test_scripts_are_included_when_configured(): void
    {
        $preRequestScript = 'console.log("Pre-request")';
        $postResponseScript = 'console.log("Post-response")';

        config([
            'api-postman.scripts.pre-request.content' => $preRequestScript,
            'api-postman.scripts.post-response.content' => $postResponseScript,
        ]);

        $this->artisan('export:collection --format=bruno')->assertExitCode(0);

        $collection = json_decode(Storage::get('bruno/' . config('api-postman.filename')), true);

        $mainFolder = Arr::first($collection['items']);
        $firstRequest = Arr::first($mainFolder['items']);

        $this->assertEquals($preRequestScript, $firstRequest['request']['script']['req']);
        $this->assertEquals($postResponseScript, $firstRequest['request']['script']['res']);
    }

    public function test_environment_variables_format(): void
    {
        $this->artisan('export:collection --format=bruno --bearer=test-token')->assertExitCode(0);

        $collection = json_decode(Storage::get('bruno/' . config('api-postman.filename')), true);

        $environment = Arr::first($collection['environments']);

        $this->assertArrayHasKey('uid', $environment);
        $this->assertEquals('Local', $environment['name']);

        $baseUrlVar = Arr::first($environment['variables']);
        $this->assertEquals('base_url', $baseUrlVar['name']);
        $this->assertEquals('http://api.test', $baseUrlVar['value']);
        $this->assertEquals('text', $baseUrlVar['type']);
        $this->assertTrue($baseUrlVar['enabled']);
        $this->assertFalse($baseUrlVar['secret']);

        $tokenVar = Arr::where($environment['variables'], fn($var) => $var['name'] === 'token');
        $tokenVar = reset($tokenVar);
        $this->assertEquals('test-token', $tokenVar['value']);
        $this->assertTrue($tokenVar['secret']);
    }

    public function test_request_body_formats(): void
    {
        config([
            'api-postman.enable_formdata' => true,
        ]);

        $this->artisan('export:collection --format=bruno')->assertExitCode(0);

        $collection = json_decode(Storage::get('bruno/' . config('api-postman.filename')), true);
        $mainFolder = Arr::first($collection['items']);
        $request = Arr::first($mainFolder['items']);

        $body = $request['request']['body'];

        $this->assertArrayHasKey('mode', $body);
        $this->assertArrayHasKey('json', $body);
        $this->assertArrayHasKey('text', $body);
        $this->assertArrayHasKey('xml', $body);
        $this->assertArrayHasKey('graphql', $body);
        $this->assertArrayHasKey('formUrlEncoded', $body);
        $this->assertArrayHasKey('multipartForm', $body);

        if ($body['mode'] === 'formUrlEncoded') {
            $formParam = Arr::first($body['formUrlEncoded']);
            $this->assertArrayHasKey('uid', $formParam);
            $this->assertArrayHasKey('name', $formParam);
            $this->assertArrayHasKey('value', $formParam);
            $this->assertArrayHasKey('description', $formParam);
            $this->assertArrayHasKey('enabled', $formParam);
        }
    }

    public static function providerFormDataEnabled(): array
    {
        return [
            [false],
            [true],
        ];
    }
}
