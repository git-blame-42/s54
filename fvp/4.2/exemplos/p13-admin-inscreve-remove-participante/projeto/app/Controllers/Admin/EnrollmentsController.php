<?php

namespace App\Controllers\Admin;

use App\Models\Enrollment;
use App\Models\Event;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

/**
 * Inscrições de um Evento gerenciadas pelo Administrador a partir do show do Evento.
 */
class EnrollmentsController extends Controller
{
    public function create(Request $request): void
    {
        $event = $this->findEvent($request);
        if ($event === null) {
            $this->eventNotFound();
            return;
        }

        $params = $request->getParam('enrollment', []);
        $userId = is_array($params) && is_scalar($params['user_id'] ?? null) ? (string) $params['user_id'] : null;

        // As regras (usuário existe, é Participante, há vaga, sem duplicidade) ficam em Enrollment::validates().
        $enrollment = new Enrollment(['event_id' => $event->id, 'user_id' => $userId]);

        if ($enrollment->save()) {
            FlashMessage::success('Participante inscrito com sucesso!');
        } else {
            FlashMessage::danger($enrollment->errors('base') ?? 'Selecione um Participante!');
        }

        $this->redirectTo(route('admin.events.show', ['id' => $event->id]));
    }

    public function destroy(Request $request): void
    {
        $event = $this->findEvent($request);
        if ($event === null) {
            $this->eventNotFound();
            return;
        }

        // Busca pelo id da Inscrição E pelo Evento da rota: não remove Inscrição de outro Evento.
        $id = $this->routeId($request, 'id');
        $enrollment = $id === null ? null : Enrollment::findBy(['id' => $id, 'event_id' => $event->id]);

        if ($enrollment !== null && $enrollment->destroy()) {
            FlashMessage::success('Inscrição removida com sucesso!');
        } else {
            FlashMessage::danger('Inscrição não encontrada!');
        }

        $this->redirectTo(route('admin.events.show', ['id' => $event->id]));
    }

    private function findEvent(Request $request): ?Event
    {
        $id = $this->routeId($request, 'event_id');

        return $id === null ? null : Event::findById($id);
    }

    /**
     * Id vindo da rota só se for formado apenas por dígitos: '1abc' não vira 1, como faria o (int).
     */
    private function routeId(Request $request, string $key): ?int
    {
        $value = $request->getParam($key);
        $digits = is_scalar($value) ? (string) $value : '';

        return ctype_digit($digits) ? (int) $digits : null;
    }

    private function eventNotFound(): void
    {
        FlashMessage::danger('Evento não encontrado!');
        $this->redirectTo(route('admin.events.index'));
    }
}
