<?php

namespace AndreasElia\PostmanGenerator\Tests\Fixtures;

use AndreasElia\PostmanGenerator\Attributes\Collection;
use AndreasElia\PostmanGenerator\Attributes\Request;
use Illuminate\Routing\Controller;

#[Collection(name: 'Example Collection', description: 'This is an example collection')]
class ExampleController extends Controller
{
    #[Request(name: 'List examples', description: 'This is the index action', group: 'Example')]
    public function index(): string
    {
        return 'index';
    }

    #[Request(name: 'Show example', description: 'This is the show action', group: 'Example')]
    public function show(): string
    {
        return 'show';
    }

    #[Request(name: 'Create example', description: 'This is the store action', group: 'Example')]
    public function store(): string
    {
        return 'store';
    }

    #[Request(name: 'Delete example', description: 'This is the delete action', group: 'Example')]
    public function delete(): string
    {
        return 'delete';
    }

    public function showWithReflectionMethod(ExampleService $service): array
    {
        return $service->getRequestData();
    }

    public function storeWithFormRequest(ExampleFormRequest $request): string
    {
        return 'storeWithFormRequest';
    }

    public function getWithFormRequest(ExampleFormRequest $request): string
    {
        return 'getWithFormRequest';
    }

    /**
     * This is the php doc route.
     * Which is also multi-line.
     *
     * and has a blank line.
     *
     * @param  string  $non-existing  param
     */
    public function phpDocRoute(): string
    {
        return 'phpDocRoute';
    }
}
