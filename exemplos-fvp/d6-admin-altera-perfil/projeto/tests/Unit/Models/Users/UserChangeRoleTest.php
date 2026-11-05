<?php

namespace Tests\Unit\Models\Users;

use App\Models\User;
use Tests\TestCase;

class UserChangeRoleTest extends TestCase
{
    private User $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = new User([
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $this->user->save();
    }

    public function test_change_role_should_promote_participant_to_admin(): void
    {
        $this->assertTrue($this->user->changeRole('admin'));

        $this->assertTrue(User::findById($this->user->id)->isAdmin());
    }

    public function test_change_role_should_demote_admin_to_participant(): void
    {
        $this->user->changeRole('admin');

        $this->assertTrue($this->user->changeRole('participant'));

        $this->assertFalse(User::findById($this->user->id)->isAdmin());
    }

    public function test_change_role_should_reject_unknown_role(): void
    {
        $this->assertFalse($this->user->changeRole('organizer'));

        $this->assertSame('participant', User::findById($this->user->id)->role);
    }
}
