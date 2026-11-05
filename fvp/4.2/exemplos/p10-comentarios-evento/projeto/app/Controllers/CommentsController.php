<?php

namespace App\Controllers;

use App\Models\Comment;
use App\Models\Event;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

class CommentsController extends Controller
{
    public function create(Request $request): void
    {
        $event = Event::findById((int) $request->getParam('id'));
        if ($event === null) {
            FlashMessage::danger('Evento não encontrado!');
            $this->redirectTo(route('dashboard.index'));
            return;
        }

        $comment = $event->comments()->new([
            'user_id' => $this->currentUser()?->id,
            'body' => $this->body($request),
        ]);

        if ($comment->save()) {
            FlashMessage::success('Comentário publicado!');
        } else {
            FlashMessage::danger('Comentário ' . $comment->errors('body'));
        }

        // Administrador comenta pela tela da Área Administrativa; Participante, pela tela do Evento.
        $route = $this->currentUser()?->isAdmin() ? 'admin.events.show' : 'events.show';
        $this->redirectTo(route($route, ['id' => $event->id]));
    }

    /**
     * Texto do comentário vindo de comment[body]; null se ausente ou não for string (ex.: comment[body][]).
     */
    private function body(Request $request): ?string
    {
        $params = $request->getParam('comment');
        if (!is_array($params) || !isset($params['body']) || !is_string($params['body'])) {
            return null;
        }

        return trim($params['body']);
    }
}
