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

    public function checkin(Request $request): void
    {
        $event = Event::findById((int) $request->getParam('id'));
        if ($event === null) {
            FlashMessage::danger('Evento não encontrado!');
            $this->redirectTo(route('events.index'));
            return;
        }

        $enrollment = $this->currentUser()?->enrollmentIn($event);
        if ($enrollment === null) {
            FlashMessage::danger('Você não está inscrito neste evento!');
            $this->redirectTo(route('events.show', ['id' => $event->id]));
            return;
        }

        $code = $request->getParam('code');
        $code = is_string($code) ? trim($code) : '';

        if ($enrollment->confirm($code)) {
            FlashMessage::success('Presença confirmada!');
        } else {
            FlashMessage::danger($enrollment->errors('base') ?? 'Não foi possível confirmar a presença!');
        }

        $this->redirectTo(route('events.show', ['id' => $event->id]));
    }
}
