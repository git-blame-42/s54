<?php

namespace App\Controllers;

use App\Models\Event;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

/**
 * Detalhe do Evento pelo slug, para qualquer usuário autenticado (Participante ou Administrador).
 */
class EventsController extends Controller
{
    public function show(Request $request): void
    {
        $event = Event::findBySlug((string) $request->getParam('slug'));
        if ($event === null) {
            FlashMessage::danger('Evento não encontrado!');
            $this->redirectTo(route('dashboard.index'));
            return;
        }

        $title = htmlspecialchars($event->title);
        $this->render('events/show', compact('event', 'title'));
    }
}
