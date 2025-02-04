<?php

namespace AndreasElia\PostmanGenerator\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuditLogController extends Controller
{
    public function index(): void {}

    public function store(Request $request): void {}

    public function show($id): void {}

    public function update(Request $request, ExampleModel $auditLog): void {}

    public function destroy($id): void {}
}
