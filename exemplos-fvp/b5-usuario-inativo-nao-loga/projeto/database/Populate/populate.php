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

$inactive = new User([
    'name' => 'Usuário Inativo',
    'email' => 'inativo@example.com',
    'password' => '123456',
    'password_confirmation' => '123456',
    'active' => 0
]);
$inactive->save();

echo "Usuários criados: {$admin->email} (admin), {$user->email} (participant) e {$inactive->email} (inativo), senha 123456\n";
