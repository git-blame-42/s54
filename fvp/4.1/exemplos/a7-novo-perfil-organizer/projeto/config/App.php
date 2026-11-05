<?php

namespace Config;

class App
{
    public static array $middlewareAliases = [
        'auth' => \App\Middleware\Authenticate::class,
        'admin' => \App\Middleware\Admin::class,
        'organizer' => \App\Middleware\Organizer::class,
    ];
}
