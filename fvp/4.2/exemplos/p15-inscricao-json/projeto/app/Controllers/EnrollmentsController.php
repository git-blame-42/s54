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
    /**
     * Com `Accept: application/json` responde JSON (201 ou 422/404), sem flash nem redirect;
     * sem o header, o fluxo HTML (flash + redirect para o Evento).
     */
    public function create(Request $request): void
    {
        $event = $this->findEvent($request);
        if ($event === null && $request->acceptJson()) {
            $message = 'Evento não encontrado!';
            $this->respondJson(404, ['ok' => false, 'message' => $message, 'errors' => [$message]]);
            return;
        }
        if ($event === null) {
            FlashMessage::danger('Evento não encontrado!');
            $this->redirectTo(route('events.index'));
            return;
        }

        $enrollment = new Enrollment(['event_id' => $event->id, 'user_id' => $this->currentUser()?->id]);
        $saved = $enrollment->save();

        if ($request->acceptJson()) {
            http_response_code($saved ? 201 : 422);
            $this->renderJson('enrollments/create', compact('saved', 'enrollment', 'event'));
            return;
        }

        if ($saved) {
            FlashMessage::success('Inscrição realizada com sucesso!');
        } else {
            FlashMessage::danger($enrollment->errors('base') ?? 'Não foi possível realizar a inscrição!');
        }

        $this->redirectTo(route('events.show', ['id' => $event->id]));
    }

    /**
     * Evento do `{id}` da rota; id que não é inteiro positivo (ex.: "abc", "1x", "-3") é tratado como
     * Evento inexistente, sem conversão silenciosa.
     */
    private function findEvent(Request $request): ?Event
    {
        $id = filter_var($request->getParam('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : Event::findById($id);
    }

    /**
     * Resposta JSON sem view (só para o "Evento não encontrado", que não tem Inscrição para mostrar).
     *
     * @param array<string, mixed> $data
     */
    private function respondJson(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
    }
}
