<?php

namespace App\Controllers;

use Core\Http\Controllers\Controller;
use Lib\Authentication\Auth;

class DashboardController extends Controller
{
    public function index(): void
    {
        $title = 'Painel do Usuário';
        $previousLoginAt = Auth::previousLoginAt();
        $this->render('dashboard/index', compact('title', 'previousLoginAt'));
    }
}
