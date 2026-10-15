<?php

require __DIR__ . '/../../config/bootstrap.php';

use App\Models\Event;
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

$events = [
    ['title' => 'Semana Acadêmica', 'starts_at' => '2026-10-20 19:30:00'],
    ['title' => 'Palestra de Abertura', 'starts_at' => '2026-10-21 19:00:00'],
    ['title' => 'Workshop de PHP', 'starts_at' => '2026-10-22 14:00:00'],
];

foreach ($events as $params) {
    $user->events()->new($params)->save();
}

echo "Usuários criados: {$admin->email} (admin) e {$user->email} (participant), senha 123456\n";
echo count($events) . " eventos criados para {$user->email}\n";
