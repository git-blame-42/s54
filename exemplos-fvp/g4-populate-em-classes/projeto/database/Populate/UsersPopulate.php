<?php

namespace Database\Populate;

use App\Models\User;

class UsersPopulate
{
    public static function populate(): void
    {
        $admin = new User([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin'
        ]);
        $admin->save();

        $user = new User([
            'name' => 'Usuário',
            'email' => 'user@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $user->save();

        echo "Usuários criados: {$admin->email} (admin) e {$user->email} (participant), senha 123456\n";
    }
}
