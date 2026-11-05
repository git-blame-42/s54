<?php

namespace App\Controllers\Admin;

use App\Models\Event;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

/**
 * Inscritos de um Evento, em página própria e paginada (área administrativa).
 */
class EnrollmentsController extends Controller
{
    private const PER_PAGE = 5;

    public function index(Request $request): void
    {
        $id = $this->positiveInt($request->getParam('id'));
        $event = $id === null ? null : Event::findById($id);
        if ($event === null) {
            FlashMessage::danger('Evento não encontrado!');
            $this->redirectTo(route('admin.events.index'));
            return;
        }

        // Sem {page} na rota é a página 1; "abc", "0" ou "-1" não viram página 1 em silêncio.
        $page = $this->positiveInt($request->getParam('page', '1'));
        if ($page === null) {
            FlashMessage::danger('Página inválida!');
            $this->redirectTo(route('admin.events.enrollments.index', ['id' => $event->id]));
            return;
        }

        $paginator = $event->enrollments()->paginate(
            page: $page,
            per_page: self::PER_PAGE,
            route: 'admin.events.enrollments.paginate',
            routeParams: ['id' => $event->id]
        );
        $enrollments = $paginator->registers();

        $title = 'Inscritos: ' . htmlspecialchars($event->title);
        $this->render('admin/enrollments/index', compact('event', 'enrollments', 'paginator', 'title'));
    }

    /**
     * Inteiro >= 1 vindo da rota, ou null se o texto não for um inteiro positivo.
     */
    private function positiveInt(mixed $value): ?int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $number === false ? null : $number;
    }
}
