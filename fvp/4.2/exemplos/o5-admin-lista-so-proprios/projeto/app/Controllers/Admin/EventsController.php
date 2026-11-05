<?php

namespace App\Controllers\Admin;

use App\Models\Event;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

class EventsController extends Controller
{
    private const PER_PAGE = 5;

    private const FIELDS = ['title', 'description', 'kind', 'modality', 'starts_on', 'location', 'capacity'];

    /**
     * Lista só os Eventos do Administrador logado (user_id = id dele), paginados.
     */
    public function index(Request $request): void
    {
        $owner = $this->currentUser();
        if ($owner === null) {
            $this->redirectTo(route('users.login'));
            return;
        }

        $paginator = $owner->events()->paginate(
            page: max(1, (int) $request->getParam('page', 1)),
            per_page: self::PER_PAGE,
            route: 'admin.events.paginate'
        );
        $events = $paginator->registers();

        $title = 'Eventos';
        $this->render('admin/events/index', compact('events', 'paginator', 'title'));
    }

    public function show(Request $request): void
    {
        $event = $this->findEvent($request);
        if ($event === null) {
            $this->eventNotFound();
            return;
        }

        $title = htmlspecialchars($event->title);
        $this->render('admin/events/show', compact('event', 'title'));
    }

    public function new(): void
    {
        $event = $this->newEvent();

        $title = 'Novo Evento';
        $this->render('admin/events/new', compact('event', 'title'));
    }

    public function create(Request $request): void
    {
        $event = $this->newEvent();
        $this->fill($event, $request);

        if ($event->save()) {
            FlashMessage::success('Evento cadastrado com sucesso!');
            $this->redirectTo(route('admin.events.show', ['id' => $event->id]));
        } else {
            FlashMessage::danger('Existem dados incorretos! Por favor, verifique!');
            $title = 'Novo Evento';
            $this->render('admin/events/new', compact('event', 'title'));
        }
    }

    public function edit(Request $request): void
    {
        $event = $this->findEvent($request);
        if ($event === null) {
            $this->eventNotFound();
            return;
        }

        $title = 'Editar Evento';
        $this->render('admin/events/edit', compact('event', 'title'));
    }

    public function update(Request $request): void
    {
        $event = $this->findEvent($request);
        if ($event === null) {
            $this->eventNotFound();
            return;
        }
        $this->fill($event, $request);

        if ($event->save()) {
            FlashMessage::success('Evento atualizado com sucesso!');
            $this->redirectTo(route('admin.events.show', ['id' => $event->id]));
        } else {
            FlashMessage::danger('Existem dados incorretos! Por favor, verifique!');
            $title = 'Editar Evento';
            $this->render('admin/events/edit', compact('event', 'title'));
        }
    }

    public function destroy(Request $request): void
    {
        $event = $this->findEvent($request);
        if ($event === null) {
            $this->eventNotFound();
            return;
        }

        if ($event->safeDestroy()) {
            FlashMessage::success('Evento removido com sucesso!');
        } else {
            FlashMessage::danger($event->errors('base') ?? 'Não foi possível remover o evento!');
        }

        $this->redirectTo(route('admin.events.index'));
    }

    /**
     * Copia para o Evento só os campos do formulário (nunca user_id nem id).
     */
    private function fill(Event $event, Request $request): void
    {
        $params = $request->getParam('event', []);
        if (!is_array($params)) {
            $params = [];
        }

        foreach (self::FIELDS as $field) {
            $value = $params[$field] ?? null;
            $event->$field = is_scalar($value) ? (string) $value : null;
        }

        $audience = $params['audience'] ?? [];
        $event->setAudienceList(is_array($audience) ? array_filter($audience, 'is_scalar') : []);
    }

    /**
     * Evento novo já com user_id do Administrador logado (events()->new()).
     */
    private function newEvent(): Event
    {
        $event = $this->currentUser()?->events()->new();

        return $event instanceof Event ? $event : new Event();
    }

    /**
     * Só acha Eventos do Administrador logado: o de outro admin vira null e cai em "Evento não encontrado!"
     * (vale para show, edit, update e destroy, sem revelar que o Evento existe).
     */
    private function findEvent(Request $request): ?Event
    {
        $event = $this->currentUser()?->events()->findById((int) $request->getParam('id'));

        return $event instanceof Event ? $event : null;
    }

    private function eventNotFound(): void
    {
        FlashMessage::danger('Evento não encontrado!');
        $this->redirectTo(route('admin.events.index'));
    }
}
