<?php

namespace Tests\Unit\Lib;

use App\Models\Event;
use App\Models\User;
use Lib\Paginator;
use Tests\TestCase;

/**
 * Testa a classe Paginator diretamente (construtor, faixa exibida, páginas vazias e condições).
 * O paginate() do model já é coberto no EventTest. Sem chamar renderPagesNavigation(): ela usa route(),
 * que exige as rotas carregadas.
 */
class PaginatorTest extends TestCase
{
    private User $admin;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin = new User([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin',
        ]);
        $this->admin->save();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createEvent(array $overrides = []): Event
    {
        $event = new Event(array_merge([
            'user_id' => $this->admin->id,
            'title' => 'Semana Acadêmica',
            'description' => 'Palestras e minicursos.',
            'kind' => 'lecture',
            'modality' => 'in_person',
            'audience' => 'students,professors',
            'starts_on' => date('Y-m-d', strtotime('+30 days')),
            'location' => 'Auditório Central',
            'capacity' => 10,
        ], $overrides));
        $this->assertTrue($event->save());

        return $event;
    }

    private function createEvents(int $total): void
    {
        for ($i = 1; $i <= $total; $i++) {
            $this->createEvent(['title' => "Evento {$i}"]);
        }
    }

    /**
     * @param array<string, mixed> $conditions
     */
    private function paginator(int $page, array $conditions = []): Paginator
    {
        return new Paginator(Event::class, $page, 5, 'events', Event::columns(), $conditions, 'admin.events.paginate');
    }

    public function test_last_page_should_show_the_remaining_registers(): void
    {
        $this->createEvents(7);

        $paginator = $this->paginator(2);

        $this->assertSame(7, $paginator->totalOfRegisters());
        $this->assertSame(2, $paginator->totalOfPages());
        $this->assertSame(2, $paginator->getPage());
        $this->assertCount(2, $paginator->registers());
        $this->assertSame(2, $paginator->totalOfRegistersOfPage());
        $this->assertContainsOnlyInstancesOf(Event::class, $paginator->registers());
        $this->assertSame(['Evento 6', 'Evento 7'], array_map(fn(Event $e) => $e->title, $paginator->registers()));
        $this->assertSame('Mostrando 6 - 7 de 7', $paginator->entriesInfo());
        $this->assertTrue($paginator->hasPreviousPage());
        $this->assertFalse($paginator->hasNextPage());
    }

    public function test_first_page_entries_info(): void
    {
        $this->createEvents(7);

        $paginator = $this->paginator(1);

        $this->assertSame(5, $paginator->totalOfRegistersOfPage());
        $this->assertSame('Mostrando 1 - 5 de 7', $paginator->entriesInfo());
    }

    /**
     * Comportamento ATUAL (defeito conhecido): depois da última página não há registros e a faixa fica
     * incoerente, com início maior que o fim e maior que o total. Se a página fora do intervalo
     * passar a ser tratada, esta asserção muda.
     */
    public function test_page_after_the_last_should_be_empty_with_incoherent_entries_info(): void
    {
        $this->createEvents(7);

        $paginator = $this->paginator(3);

        $this->assertSame(7, $paginator->totalOfRegisters());
        $this->assertSame(2, $paginator->totalOfPages());
        $this->assertSame([], $paginator->registers());
        $this->assertSame(0, $paginator->totalOfRegistersOfPage());
        $this->assertSame('Mostrando 11 - 10 de 7', $paginator->entriesInfo());
        $this->assertTrue($paginator->hasPreviousPage());
        $this->assertFalse($paginator->hasNextPage());
    }

    public function test_conditions_should_filter_the_registers_and_the_totals(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->createEvent(['title' => "Palestra {$i}", 'kind' => 'lecture']);
        }
        for ($i = 1; $i <= 4; $i++) {
            $this->createEvent(['title' => "Oficina {$i}", 'kind' => 'workshop']);
        }

        $paginator = $this->paginator(1, ['kind' => 'lecture']);

        $this->assertSame(3, $paginator->totalOfRegisters());
        $this->assertSame(1, $paginator->totalOfPages());
        $this->assertCount(3, $paginator->registers());
        foreach ($paginator->registers() as $event) {
            $this->assertInstanceOf(Event::class, $event);
            $this->assertSame('lecture', $event->kind);
        }
        $this->assertSame('Mostrando 1 - 3 de 3', $paginator->entriesInfo());
        $this->assertFalse($paginator->hasNextPage());
    }

    public function test_empty_table_should_have_zero_pages(): void
    {
        $paginator = $this->paginator(1);

        $this->assertSame(0, $paginator->totalOfRegisters());
        $this->assertSame(0, $paginator->totalOfPages());
        $this->assertSame([], $paginator->registers());
        $this->assertFalse($paginator->hasPreviousPage());
        $this->assertFalse($paginator->hasNextPage());
    }
}
