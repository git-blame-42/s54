<?php

namespace App\Controllers;

use App\Models\Event;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

/**
 * Eventos na área do usuário (Participante): só listagem e detalhe, sem ações de administração.
 */
class EventsController extends Controller
{
    private const PER_PAGE = 5;

    public function index(Request $request): void
    {
        $paginator = Event::paginate(
            page: max(1, (int) $request->getParam('page', 1)),
            per_page: self::PER_PAGE,
            route: 'events.paginate'
        );
        $events = $paginator->registers();

        $title = 'Eventos';
        $this->render('events/index', compact('events', 'paginator', 'title'));
    }

    public function show(Request $request): void
    {
        $event = Event::findById((int) $request->getParam('id'));
        if ($event === null) {
            FlashMessage::danger('Evento não encontrado!');
            $this->redirectTo(route('events.index'));
            return;
        }

        $title = htmlspecialchars($event->title);
        $this->render('events/show', compact('event', 'title'));
    }
}
