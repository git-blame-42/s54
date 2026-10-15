<?php

namespace App\Controllers;

use App\Models\Event;
use Core\Exceptions\HTTPException;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

class EventsController extends Controller
{
    public function index(Request $request): void
    {
        $paginator = $this->current_user->events()->paginate(
            page: (int) $request->getParam('page', 1),
            per_page: 10
        );
        $events = $paginator->registers();

        $title = 'Meus Eventos';
        $this->render('events/index', compact('events', 'paginator', 'title'));
    }

    public function show(Request $request): void
    {
        $event = $this->findEvent($request);

        $title = "Evento #{$event->id}";
        $this->render('events/show', compact('event', 'title'));
    }

    public function new(): void
    {
        $event = $this->current_user->events()->new();

        $title = 'Novo Evento';
        $this->render('events/new', compact('event', 'title'));
    }

    public function create(Request $request): void
    {
        $event = $this->current_user->events()->new($this->eventParams($request));

        if ($event->save()) {
            FlashMessage::success('Evento criado com sucesso!');
            $this->redirectTo(route('events.index'));
        } else {
            FlashMessage::danger('Existem dados incorretos! Verifique o formulário.');
            $title = 'Novo Evento';
            $this->render('events/new', compact('event', 'title'));
        }
    }

    public function edit(Request $request): void
    {
        $event = $this->findEvent($request);

        $title = "Editar Evento #{$event->id}";
        $this->render('events/edit', compact('event', 'title'));
    }

    public function update(Request $request): void
    {
        $event = $this->findEvent($request);
        $params = $this->eventParams($request);

        $event->title = $params['title'];
        $event->starts_at = $params['starts_at'];

        if ($event->save()) {
            FlashMessage::success('Evento atualizado com sucesso!');
            $this->redirectTo(route('events.index'));
        } else {
            FlashMessage::danger('Existem dados incorretos! Verifique o formulário.');
            $title = "Editar Evento #{$event->id}";
            $this->render('events/edit', compact('event', 'title'));
        }
    }

    public function destroy(Request $request): void
    {
        $event = $this->findEvent($request);
        $event->destroy();

        FlashMessage::success('Evento removido com sucesso!');
        $this->redirectTo(route('events.index'));
    }

    /**
     * Busca o evento pelo id SÓ entre os eventos do usuário logado.
     * Evento de outro usuário (ou inexistente) responde 404.
     */
    private function findEvent(Request $request): Event
    {
        /** @var Event|null $event */
        $event = $this->current_user->events()->findById((int) $request->getParam('id'));

        if ($event === null) {
            throw new HTTPException('Evento não encontrado', 404);
        }

        return $event;
    }

    /**
     * Lista explícita de campos aceitos do formulário (nunca repassar o POST inteiro ao model).
     *
     * @return array{title: string|null, starts_at: string|null}
     */
    private function eventParams(Request $request): array
    {
        $params = $request->getParam('event', []);

        return [
            'title' => $params['title'] ?? null,
            'starts_at' => $params['starts_at'] ?? null,
        ];
    }
}
