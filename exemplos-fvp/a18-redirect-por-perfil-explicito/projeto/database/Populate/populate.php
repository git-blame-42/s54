<?php

require __DIR__ . '/../../config/bootstrap.php';

use App\Models\User;
use Core\Database\Database;

Database::migrate();

$admin = new User([
    'name' => 'Administrador',
    'email' => 'admin@example.com',
    'password' => '123456',
    'password_confirmation' => '123456',
    'role' => User::ROLE_ADMIN
]);
$admin->save();

$user = new User([
    'name' => 'Usuário',
    'email' => 'user@example.com',
    'password' => '123456',
    'password_confirmation' => '123456'
]);
$user->save();

echo "Usuários criados: {$admin->email} ({$admin->role}) e {$user->email} ({$user->role}), senha 123456\n";
