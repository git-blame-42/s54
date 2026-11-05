<?php

namespace App\Controllers\Admin;

use App\Models\Enrollment;
use App\Models\Event;
use Core\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index(): void
    {
        // Totais calculados a cada acesso (não ficam gravados no banco).
        $total = count(Event::all());
        $totalEnrollments = count(Enrollment::all());

        $title = 'Painel Administrativo';
        $this->render('admin/dashboard/index', compact('title', 'total', 'totalEnrollments'));
    }
}
