<?php

namespace App\Controllers\Admin;

use App\Models\Event;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;
use Lib\Paginator;

/**
 * CRUD de Eventos do Administrador (todos os Eventos). O Organizador reaproveita esta classe
 * (App\Controllers\Organizer\EventsController) trocando o prefixo das rotas e a busca dos Eventos.
 */
class EventsController extends Controller
{
    protected const PER_PAGE = 5;

    /** Prefixo dos nomes de rota (admin.events.index, admin.events.show...), usado também nas views. */
    protected const ROUTE_PREFIX = 'admin.events';

    private const FIELDS = ['title', 'description', 'kind', 'modality', 'starts_on', 'location', 'capacity'];

    public function index(Request $request): void
    {
        $paginator = $this->paginateEvents(max(1, (int) $request->getParam('page', 1)));
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
            $this->redirectTo($this->eventRoute('show', ['id' => $event->id]));
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
            $this->redirectTo($this->eventRoute('show', ['id' => $event->id]));
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

        $this->redirectTo($this->eventRoute('index'));
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
     * URL da ação do CRUD com o prefixo deste controller. As views chamam `$this->eventRoute('edit', [...])`.
     *
     * @param array<string, mixed> $params
     */
    public function eventRoute(string $action, array $params = []): string
    {
        return route(static::ROUTE_PREFIX . ".{$action}", $params);
    }

    /** Página da listagem: o Administrador vê todos os Eventos. */
    protected function paginateEvents(int $page): Paginator
    {
        return Event::paginate(page: $page, per_page: static::PER_PAGE, route: static::ROUTE_PREFIX . '.paginate');
    }

    /** Evento novo, já com o usuário logado como dono. */
    protected function newEvent(): Event
    {
        return new Event(['user_id' => $this->currentUser()?->id]);
    }

    protected function findEvent(Request $request): ?Event
    {
        return Event::findById((int) $request->getParam('id'));
    }

    private function eventNotFound(): void
    {
        FlashMessage::danger('Evento não encontrado!');
        $this->redirectTo($this->eventRoute('index'));
    }
}
