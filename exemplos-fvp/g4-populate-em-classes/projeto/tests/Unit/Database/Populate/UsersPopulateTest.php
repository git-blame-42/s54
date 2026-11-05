<?php

namespace Tests\Unit\Database\Populate;

use App\Models\User;
use Database\Populate\UsersPopulate;
use Tests\TestCase;

class UsersPopulateTest extends TestCase
{
    public function test_populate_should_create_admin_and_participant(): void
    {
        $output = $this->getOutput(fn() => UsersPopulate::populate());

        $this->assertCount(2, User::all());

        $admin = User::findByEmail('admin@example.com');
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->authenticate('123456'));

        $user = User::findByEmail('user@example.com');
        $this->assertNotNull($user);
        $this->assertSame('participant', $user->role);
        $this->assertTrue($user->authenticate('123456'));

        $this->assertStringContainsString('Usuários criados', $output);
    }
}
