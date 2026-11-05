<?php

namespace App\Controllers\Admin;

use App\Models\User;
use Core\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index(): void
    {
        $totalUsers = count(User::all());
        $totalAdmins = User::countByRole('admin');
        $totalParticipants = User::countByRole('participant');

        $title = 'Painel Administrativo';
        $this->render(
            'admin/dashboard/index',
            compact('title', 'totalUsers', 'totalAdmins', 'totalParticipants')
        );
    }
}
