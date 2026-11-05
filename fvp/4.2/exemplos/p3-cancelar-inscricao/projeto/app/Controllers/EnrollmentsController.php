<?php

namespace App\Controllers;

use App\Models\Enrollment;
use App\Models\Event;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

/**
 * Inscrição do usuário logado em um Evento (área do Participante).
 */
class EnrollmentsController extends Controller
{
    public function create(Request $request): void
    {
        $event = Event::findById((int) $request->getParam('id'));
        if ($event === null) {
            FlashMessage::danger('Evento não encontrado!');
            $this->redirectTo(route('events.index'));
            return;
        }

        $enrollment = new Enrollment(['event_id' => $event->id, 'user_id' => $this->currentUser()?->id]);

        if ($enrollment->save()) {
            FlashMessage::success('Inscrição realizada com sucesso!');
        } else {
            FlashMessage::danger($enrollment->errors('base') ?? 'Não foi possível realizar a inscrição!');
        }

        $this->redirectTo(route('events.show', ['id' => $event->id]));
    }

    /**
     * Cancela a Inscrição do usuário logado: a busca usa o id do Evento da rota e o usuário da sessão.
     */
    public function destroy(Request $request): void
    {
        $eventId = (int) $request->getParam('id');
        $enrollment = Enrollment::findBy(['event_id' => $eventId, 'user_id' => $this->currentUser()?->id]);

        if ($enrollment === null) {
            FlashMessage::danger('Você não está inscrito neste evento!');
        } elseif ($enrollment->destroy()) {
            FlashMessage::success('Inscrição cancelada!');
        } else {
            FlashMessage::danger('Não foi possível cancelar a inscrição!');
        }

        $this->redirectTo(route('events.show', ['id' => $eventId]));
    }
}
