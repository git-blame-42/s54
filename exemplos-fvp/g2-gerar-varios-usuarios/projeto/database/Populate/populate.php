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

// Participantes extras só para demonstrar a paginação da listagem de usuários:
// com 10 por página, os 17 usuários (2 acima + 15 aqui) ocupam 2 páginas.
$participantsCount = 15;
for ($i = 1; $i <= $participantsCount; $i++) {
    (new User([
        'name' => "Participante {$i}",
        'email' => "participante{$i}@example.com",
        'password' => '123456',
        'password_confirmation' => '123456'
    ]))->save();
}

$totalUsers = count(User::all());

echo "Usuários criados: {$admin->email} (admin) e {$user->email} (participant), senha 123456\n";
echo "Participantes extras: participante1@example.com a participante{$participantsCount}@example.com, senha 123456\n";
echo "Total de usuários no banco: {$totalUsers}\n";
