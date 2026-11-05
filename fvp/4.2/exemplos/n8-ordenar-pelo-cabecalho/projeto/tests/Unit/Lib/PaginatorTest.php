<?php

namespace Tests\Unit\Lib;

use App\Models\Event;
use App\Models\User;
use Core\Constants\Constants;
use Core\Router\Router;
use Tests\TestCase;

class PaginatorTest extends TestCase
{
    private User $admin;

    public function setUp(): void
    {
        parent::setUp();
        // Os links de página usam route(): carrega as rotas, como o ControllerTestCase.
        Router::reset();
        require Constants::rootPath()->join('config/routes.php');

        $this->admin = new User([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin',
        ]);
        $this->admin->save();

        // Cadastrados fora da ordem alfabética: a ordem de id não coincide com a de título.
        foreach (['Delta', 'Alfa', 'Eco', 'Charlie', 'Bravo'] as $title) {
            $event = new Event([
                'user_id' => $this->admin->id,
                'title' => $title,
                'description' => 'Descrição.',
                'kind' => 'lecture',
                'modality' => 'in_person',
                'audience' => 'students',
                'starts_on' => date('Y-m-d', strtotime('+10 days')),
                'location' => 'Sala 1',
                'capacity' => 10,
            ]);
            $event->save();
        }
    }

    /**
     * @param array<int, Event> $events
     * @return array<int, string>
     */
    private function titles(array $events): array
    {
        return array_map(fn(Event $event) => $event->title, $events);
    }

    public function test_without_order_by_should_keep_the_database_order(): void
    {
        $paginator = Event::paginate(1, 3);

        $this->assertSame(['Delta', 'Alfa', 'Eco'], $this->titles($paginator->registers()));
    }

    public function test_order_by_should_be_applied_before_the_limit(): void
    {
        $page1 = Event::paginate(1, 3, orderBy: 'title ASC, id ASC');
        $page2 = Event::paginate(2, 3, orderBy: 'title ASC, id ASC');

        $this->assertSame(['Alfa', 'Bravo', 'Charlie'], $this->titles($page1->registers()));
        $this->assertSame(['Delta', 'Eco'], $this->titles($page2->registers()));
        $this->assertEquals(5, $page1->totalOfRegisters());
    }

    public function test_order_by_desc(): void
    {
        $paginator = Event::paginate(1, 2, orderBy: 'title DESC, id ASC');

        $this->assertSame(['Eco', 'Delta'], $this->titles($paginator->registers()));
    }

    public function test_query_params_should_default_to_empty(): void
    {
        $this->assertSame([], Event::paginate(1, 3)->queryParams());
    }

    public function test_query_params_should_be_kept_in_the_page_links(): void
    {
        $paginator = Event::paginate(
            1,
            2,
            'admin.events.paginate',
            'title DESC, id ASC',
            ['sort' => 'title', 'dir' => 'desc']
        );

        $this->assertSame(['sort' => 'title', 'dir' => 'desc'], $paginator->queryParams());

        $html = $this->getOutput(fn() => $paginator->renderPagesNavigation());

        $this->assertStringContainsString('href="/admin/events/page/2?sort=title&dir=desc"', $html);
        $this->assertStringContainsString('href="/admin/events/page/3?sort=title&dir=desc"', $html);
    }

    public function test_page_links_without_query_params_stay_unchanged(): void
    {
        $html = $this->getOutput(fn() => Event::paginate(1, 2, 'admin.events.paginate')->renderPagesNavigation());

        $this->assertStringContainsString('href="/admin/events/page/2"', $html);
        $this->assertStringNotContainsString('?', $html);
    }
}
