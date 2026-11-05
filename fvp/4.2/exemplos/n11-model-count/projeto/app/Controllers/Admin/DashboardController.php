<?php

namespace App\Controllers\Admin;

use App\Models\Event;
use Core\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index(): void
    {
        $eventsCount = Event::count();

        $title = 'Painel Administrativo';
        $this->render('admin/dashboard/index', compact('eventsCount', 'title'));
    }
}
