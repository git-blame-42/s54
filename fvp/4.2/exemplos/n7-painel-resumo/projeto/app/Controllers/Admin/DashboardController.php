<?php

namespace App\Controllers\Admin;

use App\Models\Enrollment;
use App\Models\Event;
use Core\Http\Controllers\Controller;

class DashboardController extends Controller
{
    private const UPCOMING_LIMIT = 3;

    public function index(): void
    {
        $countByKind = Event::countByKind();
        $totalEvents = array_sum($countByKind);
        $totalEnrollments = Enrollment::total();
        $upcoming = Event::upcoming(self::UPCOMING_LIMIT);

        $title = 'Painel Administrativo';
        $this->render(
            'admin/dashboard/index',
            compact('countByKind', 'totalEvents', 'totalEnrollments', 'upcoming', 'title')
        );
    }
}
