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

    public function index(Request $request): void
    {
        $paginator = Event::paginate(
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
        $event = new Event();
        // O formulário abre com o admin logado como responsável.
        $event->user_id = $this->currentUser()?->id;

        $title = 'Novo Evento';
        $this->render('admin/events/new', compact('event', 'title'));
    }

    public function create(Request $request): void
    {
        $event = new Event();
        // Padrão: o admin logado; fill() troca pelo responsável escolhido no formulário, se vier.
        $event->user_id = $this->currentUser()?->id;
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
     * Copia para o Evento só os campos do formulário (nunca id). O responsável (user_id) só é trocado se vier
     * preenchido; ausente, fica o atual (o logado no create, o gravado no update). Quem valida é o model.
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

        $userId = $params['user_id'] ?? null;
        if (is_scalar($userId) && (string) $userId !== '') {
            $event->user_id = (string) $userId;
        }

        $audience = $params['audience'] ?? [];
        $event->setAudienceList(is_array($audience) ? array_filter($audience, 'is_scalar') : []);
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
