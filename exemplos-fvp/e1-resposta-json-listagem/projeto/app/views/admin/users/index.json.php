<?php

// Preenche $json, que o Controller::renderJson() serializa com json_encode().
$json['users'] = array_map(
    fn ($user) => [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'role' => $user->role,
        'last_login_at' => $user->last_login_at,
    ],
    $users
);

$json['pagination'] = [
    'page' => $paginator->getPage(),
    'per_page' => $paginator->perPage(),
    'total_of_registers' => $paginator->totalOfRegisters(),
    'total_of_pages' => $paginator->totalOfPages(),
];
