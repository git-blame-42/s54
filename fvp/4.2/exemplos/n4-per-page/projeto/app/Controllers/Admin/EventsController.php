<?php

namespace App\Controllers\Admin;

use App\Models\Event;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

class EventsController extends Controller
{
    private const PER_PAGE = 10;

    /** Valores aceitos em `?per_page=` (lista fechada: 0 daria divisão por zero no Paginator). */
    private const PER_PAGE_OPTIONS = [5, 10, 20];

    private const FIELDS = ['title', 'description', 'kind', 'modality', 'starts_on', 'location', 'capacity'];

    public function index(Request $request): void
    {
        $perPage = $this->perPage($request);

        $paginator = Event::paginate(
            page: max(1, (int) $request->getParam('page', 1)),
            per_page: $perPage,
            route: 'admin.events.paginate',
            queryParams: $perPage !== self::PER_PAGE ? ['per_page' => $perPage] : []
        );
        $events = $paginator->registers();

        $perPageOptions = self::PER_PAGE_OPTIONS;

        $title = 'Eventos';
        $this->render('admin/events/index', compact('events', 'paginator', 'perPageOptions', 'title'));
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
        $event = new Event();

        $title = 'Novo Evento';
        $this->render('admin/events/new', compact('event', 'title'));
    }

    public function create(Request $request): void
    {
        $event = new Event();
        $this->fill($event, $request);
        $event->user_id = $this->currentUser()?->id;

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
     * `?per_page=` só vale se estiver na lista fechada; qualquer outro valor (0, texto, array) usa o padrão.
     */
    private function perPage(Request $request): int
    {
        $perPage = $request->getParam('per_page');
        if (!is_scalar($perPage) || !ctype_digit((string) $perPage)) {
            return self::PER_PAGE;
        }

        return in_array((int) $perPage, self::PER_PAGE_OPTIONS, true) ? (int) $perPage : self::PER_PAGE;
    }

    private function findEvent(Request $request): ?Event
    {
        return Event::findById((int) $request->getParam('id'));
    }

    private function eventNotFound(): void
    {
        FlashMessage::danger('Evento não encontrado!');
        $this->redirectTo(route('admin.events.index'));
    }
}
