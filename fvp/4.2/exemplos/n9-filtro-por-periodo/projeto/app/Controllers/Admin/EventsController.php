<?php

namespace App\Controllers\Admin;

use App\Models\Event;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use DateTimeImmutable;
use Lib\FlashMessage;

class EventsController extends Controller
{
    private const PER_PAGE = 5;

    private const FIELDS = ['title', 'description', 'kind', 'modality', 'starts_on', 'location', 'capacity'];

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->getParam('page', 1));
        $from = $this->dateParam($request, 'from');
        $to = $this->dateParam($request, 'to');
        $error = $this->periodError($from, $to);

        if ($error === null && ($from !== '' || $to !== '')) {
            $paginator = Event::paginateBetween(
                from: $from !== '' ? $from : null,
                to: $to !== '' ? $to : null,
                page: $page,
                per_page: self::PER_PAGE,
                route: 'admin.events.paginate'
            );
        } else {
            if ($error !== null) {
                FlashMessage::danger("{$error} A listagem abaixo está sem filtro.");
            }
            $paginator = Event::paginate(page: $page, per_page: self::PER_PAGE, route: 'admin.events.paginate');
        }
        $events = $paginator->registers();

        $title = 'Eventos';
        $this->render('admin/events/index', compact('events', 'paginator', 'title', 'from', 'to', 'error'));
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
     * Valor bruto de um parâmetro de data do filtro: '' quando ausente ou vazio; um array
     * (`?from[]=x`) vira um valor inválido, para ser acusado e não ignorado.
     */
    private function dateParam(Request $request, string $name): string
    {
        $value = $request->getParam($name, '');

        if ($value === null) {
            return '';
        }

        return is_string($value) ? trim($value) : '!';
    }

    /**
     * Validação de entrada do filtro (não é regra de negócio do Evento): datas no formato
     * AAAA-MM-DD que existam no calendário e `from` <= `to`. Retorna a mensagem de erro ou null.
     */
    private function periodError(string $from, string $to): ?string
    {
        foreach (['inicial' => $from, 'final' => $to] as $label => $value) {
            if ($value !== '' && !$this->isValidDate($value)) {
                return "Data {$label} inválida no filtro!";
            }
        }

        if ($from !== '' && $to !== '' && $from > $to) {
            return 'A data inicial do filtro não pode ser depois da data final!';
        }

        return null;
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
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
