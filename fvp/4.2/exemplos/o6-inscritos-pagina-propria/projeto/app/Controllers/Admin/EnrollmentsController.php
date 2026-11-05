<?php

namespace App\Controllers\Admin;

use App\Models\Event;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

/**
 * Inscritos de um Evento, em página própria e paginada (área administrativa).
 */
class EnrollmentsController extends Controller
{
    private const PER_PAGE = 5;

    public function index(Request $request): void
    {
        $event = Event::findById((int) $request->getParam('id'));
        if ($event === null) {
            FlashMessage::danger('Evento não encontrado!');
            $this->redirectTo(route('admin.events.index'));
            return;
        }

        $paginator = $event->enrollments()->paginate(
            page: max(1, (int) $request->getParam('page', 1)),
            per_page: self::PER_PAGE,
            route: 'admin.events.enrollments.paginate',
            routeParams: ['id' => $event->id]
        );
        $enrollments = $paginator->registers();

        $title = 'Inscritos: ' . htmlspecialchars($event->title);
        $this->render('admin/enrollments/index', compact('event', 'enrollments', 'paginator', 'title'));
    }
}
