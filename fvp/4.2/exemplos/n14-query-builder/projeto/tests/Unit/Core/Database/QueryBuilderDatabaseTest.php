<?php

namespace Tests\Unit\Core\Database;

use App\Models\Event;
use App\Models\User;
use Tests\TestCase;

/**
 * get() e paginate() do QueryBuilder contra o banco de teste.
 */
class QueryBuilderDatabaseTest extends TestCase
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

    private function createEvent(string $title, string $kind, int $days): Event
    {
        $event = new Event([
            'user_id' => $this->admin->id,
            'title' => $title,
            'description' => 'Descrição.',
            'kind' => $kind,
            'modality' => 'in_person',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime("+{$days} days")),
            'location' => 'Auditório',
            'capacity' => 10,
        ]);
        $this->assertTrue($event->save());

        return $event;
    }

    /**
     * @param array<Event> $events
     * @return array<int, string>
     */
    private function titles(array $events): array
    {
        return array_map(fn(Event $event) => $event->title, $events);
    }

    public function test_get_should_filter_and_order_in_the_database(): void
    {
        $this->createEvent('Palestra 30', 'lecture', 30);
        $this->createEvent('Oficina 10', 'workshop', 10);
        $this->createEvent('Palestra 5', 'lecture', 5);
        $this->createEvent('Palestra 20', 'lecture', 20);

        $events = Event::query()->where('kind', 'lecture')->orderBy('starts_on', 'DESC')->get();

        $this->assertContainsOnlyInstancesOf(Event::class, $events);
        $this->assertSame(['Palestra 30', 'Palestra 20', 'Palestra 5'], $this->titles($events));
    }

    public function test_get_with_same_column_twice_should_filter_a_range(): void
    {
        foreach ([5, 10, 15, 20] as $days) {
            $this->createEvent("Evento {$days}", 'lecture', $days);
        }

        $events = Event::query()
            ->where('starts_on', '>=', date('Y-m-d', strtotime('+10 days')))
            ->where('starts_on', '<=', date('Y-m-d', strtotime('+15 days')))
            ->orderBy('starts_on')
            ->get();

        $this->assertSame(['Evento 10', 'Evento 15'], $this->titles($events));
    }

    public function test_get_should_bind_values_instead_of_concatenating(): void
    {
        $this->createEvent('Oficina de PHP', 'workshop', 5);
        $this->createEvent('Palestra', 'lecture', 6);

        $this->assertSame([], Event::query()->where('title', "x' OR '1' = '1")->get());
        $this->assertSame(
            ['Oficina de PHP'],
            $this->titles(Event::query()->where('title', 'LIKE', '%Oficina%')->get())
        );
    }

    public function test_paginate_should_count_only_filtered_rows_and_keep_the_order(): void
    {
        // Palestras cadastradas fora de ordem de data, misturadas com Oficinas.
        foreach ([35, 5, 25, 15, 30, 10, 20] as $days) {
            $this->createEvent("Palestra {$days}", 'lecture', $days);
            $this->createEvent("Oficina {$days}", 'workshop', $days);
        }

        $query = Event::query()->where('kind', 'lecture')->orderBy('starts_on');
        $page1 = $query->paginate(page: 1, perPage: 5, route: 'admin.events.paginate');
        $page2 = $query->paginate(page: 2, perPage: 5, route: 'admin.events.paginate');

        $this->assertSame(7, $page1->totalOfRegisters());
        $this->assertSame(2, $page1->totalOfPages());
        $this->assertSame('admin.events.paginate', $page1->getRouteName());
        $this->assertSame(
            ['Palestra 5', 'Palestra 10', 'Palestra 15', 'Palestra 20', 'Palestra 25'],
            $this->titles($page1->registers())
        );
        $this->assertSame(['Palestra 30', 'Palestra 35'], $this->titles($page2->registers()));
        $this->assertSame('Mostrando 6 - 7 de 7', $page2->entriesInfo());
    }
}
