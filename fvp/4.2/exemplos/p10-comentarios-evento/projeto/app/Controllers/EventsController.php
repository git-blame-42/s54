<?php

namespace App\Controllers;

use App\Models\Event;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

/**
 * Tela do Evento para qualquer usuário logado (Participante ou Administrador): dados e comentários.
 */
class EventsController extends Controller
{
    public function show(Request $request): void
    {
        $event = Event::findById((int) $request->getParam('id'));
        if ($event === null) {
            FlashMessage::danger('Evento não encontrado!');
            $this->redirectTo(route('dashboard.index'));
            return;
        }

        $title = htmlspecialchars($event->title);
        $this->render('events/show', compact('event', 'title'));
    }
}
