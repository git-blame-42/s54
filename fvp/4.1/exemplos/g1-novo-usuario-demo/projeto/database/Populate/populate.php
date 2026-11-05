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

$manager = new User([
    'name' => 'Gestor',
    'email' => 'gestor@example.com',
    'password' => '123456',
    'password_confirmation' => '123456',
    'role' => 'admin'
]);
$manager->save();

$user = new User([
    'name' => 'Usuário',
    'email' => 'user@example.com',
    'password' => '123456',
    'password_confirmation' => '123456'
]);
$user->save();

$maria = new User([
    'name' => 'Maria Participante',
    'email' => 'maria@example.com',
    'password' => '123456',
    'password_confirmation' => '123456'
]);
$maria->save();

echo "Usuários criados (senha 123456):\n";
foreach ([$admin, $manager, $user, $maria] as $created) {
    echo "  {$created->email} ({$created->role})\n";
}
