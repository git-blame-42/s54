<?php

namespace App\Controllers;

use Core\Http\Controllers\Controller;

class ProfileController extends Controller
{
    public function show(): void
    {
        $title = 'Meu Perfil';
        $this->render('profile/show', compact('title'));
    }
}
