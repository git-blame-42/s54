<?php

namespace App\Controllers\Organizer;

use App\Controllers\Admin\EventsController as AdminEventsController;
use App\Models\Event;
use App\Models\User;
use Core\Http\Request;
use LogicException;
use Lib\Paginator;

/**
 * CRUD de Eventos do Organizador: o mesmo do Administrador (ações, views e validações), mas
 * só com os Eventos dele (user_id = id do logado), pela relação User::events().
 */
class EventsController extends AdminEventsController
{
    protected const ROUTE_PREFIX = 'organizer.events';

    protected function paginateEvents(int $page): Paginator
    {
        return $this->owner()->events()->paginate(
            page: $page,
            per_page: static::PER_PAGE,
            route: static::ROUTE_PREFIX . '.paginate'
        );
    }

    protected function newEvent(): Event
    {
        $event = $this->owner()->events()->new();

        return $event instanceof Event ? $event : new Event();
    }

    /**
     * Evento de outro usuário vira null e cai em "Evento não encontrado!" (show, edit, update, destroy).
     */
    protected function findEvent(Request $request): ?Event
    {
        $event = $this->owner()->events()->findById((int) $request->getParam('id'));

        return $event instanceof Event ? $event : null;
    }

    private function owner(): User
    {
        $user = $this->currentUser();
        if ($user === null) {
            // Não acontece: as rotas do Organizador ficam dentro do grupo 'auth'.
            throw new LogicException('Área do Organizador acessada sem usuário logado.');
        }

        return $user;
    }
}
