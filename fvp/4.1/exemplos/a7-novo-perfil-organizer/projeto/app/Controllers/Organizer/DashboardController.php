<?php

namespace App\Controllers\Organizer;

use Core\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index(): void
    {
        $title = 'Painel do Organizador';
        $this->render('organizer/dashboard/index', compact('title'));
    }
}
