<?php

namespace ${NAMESPACE};

use App\Models\User;
use Lib\Authentication\Auth;
use Tests\Integration\Controllers\ControllerTestCase;

class ${NAME} extends ControllerTestCase
{
    private const CONTROLLER = 'App\Controllers\\${controller}';

    private User ${DS}admin;

    public function setUp(): void
    {
        parent::setUp();
        ${DS}_SESSION['flash'] = [];

        ${DS}this->admin = new User([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin'
        ]);
        ${DS}this->admin->save();
        Auth::login(${DS}this->admin);
    }

    public function tearDown(): void
    {
        unset(${DS}_SESSION['flash']);
        parent::tearDown();
    }

    public function test_index_renders_list(): void
    {
        ${DS}response = ${DS}this->get(action: 'index', controllerName: self::CONTROLLER);

        ${DS}this->assertStringContainsString('${texto}', ${DS}response);
    }
}
