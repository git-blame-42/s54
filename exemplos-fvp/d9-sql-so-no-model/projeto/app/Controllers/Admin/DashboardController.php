<?php

namespace App\Controllers\Admin;

use App\Models\User;
use Core\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index(): void
    {
        $users = User::orderedByName();
        $totalAdmins = User::countByRole('admin');
        $totalParticipants = User::countByRole('participant');
        $title = 'Painel Administrativo';
        $this->render(
            'admin/dashboard/index',
            compact('users', 'totalAdmins', 'totalParticipants', 'title')
        );
    }
}
