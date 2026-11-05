<?php

namespace Tests\Unit\Core\Router;

use Core\Http\Request;

class MockResourceController
{
    public function index(Request $request): void
    {
        echo 'index page=' . $request->getParam('page', '1');
    }

    public function new(Request $request): void
    {
        echo 'new';
    }

    public function create(Request $request): void
    {
        echo 'create';
    }

    public function show(Request $request): void
    {
        echo 'show id=' . $request->getParam('id');
    }

    public function edit(Request $request): void
    {
        echo 'edit id=' . $request->getParam('id');
    }

    public function update(Request $request): void
    {
        echo 'update id=' . $request->getParam('id');
    }

    public function destroy(Request $request): void
    {
        echo 'destroy id=' . $request->getParam('id');
    }
}
