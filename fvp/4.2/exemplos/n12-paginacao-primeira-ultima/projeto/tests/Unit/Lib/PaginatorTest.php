<?php

namespace Tests\Unit\Lib;

use App\Models\Event;
use App\Models\User;
use Lib\Paginator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Paginator: firstPage(), lastPage() e pagesWindow() (janela de números em volta da página atual).
 */
class PaginatorTest extends TestCase
{
    private function createEvents(int $total): void
    {
        $admin = new User([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin',
        ]);
        $admin->save();

        for ($i = 1; $i <= $total; $i++) {
            $event = new Event([
                'user_id' => $admin->id,
                'title' => "Evento {$i}",
                'description' => 'Descrição.',
                'kind' => 'lecture',
                'modality' => 'online',
                'audience' => 'students',
                'starts_on' => date('Y-m-d', strtotime('+10 days')),
                'location' => 'Online',
                'capacity' => 10,
            ]);
            $this->assertTrue($event->save());
        }
    }

    private function paginator(int $page, int $perPage): Paginator
    {
        return Event::paginate(page: $page, per_page: $perPage, route: 'admin.events.paginate');
    }

    /**
     * 7 Eventos, 1 por página = 7 páginas. Página 0 não entra: o Paginator calcula OFFSET negativo e o
     * MySQL recusa; o controller já usa max(1, page).
     *
     * @return array<string, array{int, array<int>}>
     */
    public static function windowProvider(): array
    {
        return [
            'início (página 1)' => [1, [1, 2, 3]],
            'segunda página' => [2, [1, 2, 3]],
            'meio (página 4)' => [4, [3, 4, 5]],
            'penúltima' => [6, [5, 6, 7]],
            'fim (página 7)' => [7, [5, 6, 7]],
            'página 99 (fora, depois)' => [99, [5, 6, 7]],
        ];
    }

    /**
     * @param array<int> $expected
     */
    #[DataProvider('windowProvider')]
    public function test_pages_window_should_be_centered_and_respect_the_limits(int $page, array $expected): void
    {
        $this->createEvents(7);

        $this->assertSame($expected, $this->paginator($page, 1)->pagesWindow());
    }

    public function test_first_and_last_page(): void
    {
        $this->createEvents(16);

        $paginator = $this->paginator(2, 5);

        $this->assertSame(1, $paginator->firstPage());
        $this->assertSame(4, $paginator->lastPage());
        $this->assertSame([1, 2, 3], $paginator->pagesWindow());
        $this->assertSame([2, 3, 4], $this->paginator(4, 5)->pagesWindow());
    }

    public function test_single_page_should_have_a_window_with_only_page_one(): void
    {
        $this->createEvents(3);

        $paginator = $this->paginator(1, 5);

        $this->assertSame(1, $paginator->lastPage());
        $this->assertSame([1], $paginator->pagesWindow());
    }

    public function test_without_records_last_page_should_be_one(): void
    {
        $paginator = $this->paginator(1, 5);

        $this->assertSame(0, $paginator->totalOfPages());
        $this->assertSame(1, $paginator->lastPage());
        $this->assertSame([1], $paginator->pagesWindow());
    }

    public function test_window_should_not_be_larger_than_the_number_of_pages(): void
    {
        $this->createEvents(2);

        $this->assertSame([1, 2], $this->paginator(1, 1)->pagesWindow());
        $this->assertSame([1, 2], $this->paginator(2, 1)->pagesWindow(5));
    }

    public function test_window_size_can_be_changed(): void
    {
        $this->createEvents(7);

        $this->assertSame([2, 3, 4, 5, 6], $this->paginator(4, 1)->pagesWindow(5));
        $this->assertSame([3, 4, 5, 6], $this->paginator(4, 1)->pagesWindow(4));
        $this->assertSame([4], $this->paginator(4, 1)->pagesWindow(1));
        $this->assertSame([4], $this->paginator(4, 1)->pagesWindow(0));
    }
}
