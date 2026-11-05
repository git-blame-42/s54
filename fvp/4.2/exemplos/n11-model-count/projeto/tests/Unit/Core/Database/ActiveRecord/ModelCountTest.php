<?php

namespace Tests\Unit\Core\Database\ActiveRecord;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\User;
use Tests\TestCase;

/**
 * Model::count() (SELECT COUNT(*) com condições de igualdade) e HasMany::count().
 */
class ModelCountTest extends TestCase
{
    private User $admin;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUser('Administrador', 'admin@example.com', 'admin');
    }

    private function createUser(string $name, string $email, string $role = 'participant'): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createEvent(array $overrides = [], ?User $creator = null): Event
    {
        $event = new Event(array_merge([
            'user_id' => ($creator ?? $this->admin)->id,
            'title' => 'Evento',
            'description' => 'Descrição.',
            'kind' => 'lecture',
            'modality' => 'online',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Online',
            'capacity' => 10,
        ], $overrides));
        $this->assertTrue($event->save());

        return $event;
    }

    public function test_count_should_be_zero_for_an_empty_table(): void
    {
        $this->assertSame(0, Event::count());
        $this->assertSame(0, Enrollment::count());
    }

    public function test_count_without_conditions_should_count_every_record(): void
    {
        $this->createEvent(['title' => 'Primeiro']);
        $this->createEvent(['title' => 'Segundo']);
        $this->createEvent(['title' => 'Terceiro']);

        $this->assertSame(3, Event::count());
        $this->assertSame(count(Event::all()), Event::count());
    }

    public function test_count_with_conditions_should_count_only_matching_records(): void
    {
        $this->createEvent(['title' => 'Palestra 1', 'kind' => 'lecture', 'modality' => 'online']);
        $this->createEvent(['title' => 'Palestra 2', 'kind' => 'lecture', 'modality' => 'in_person']);
        $this->createEvent(['title' => 'Oficina', 'kind' => 'workshop', 'modality' => 'online']);

        $this->assertSame(2, Event::count(['kind' => 'lecture']));
        $this->assertSame(1, Event::count(['kind' => 'lecture', 'modality' => 'online']));
        $this->assertSame(count(Event::where(['kind' => 'lecture'])), Event::count(['kind' => 'lecture']));
    }

    public function test_count_without_matches_should_be_zero(): void
    {
        $this->createEvent();

        $this->assertSame(0, Event::count(['kind' => 'academic_week']));
        $this->assertSame(0, User::count(['email' => 'ninguem@example.com']));
    }

    public function test_count_should_bind_values_instead_of_concatenating_them(): void
    {
        $this->createEvent(['title' => "Evento d'Água"]);

        $this->assertSame(1, Event::count(['title' => "Evento d'Água"]));
        $this->assertSame(0, Event::count(['title' => "x' OR '1'='1"]));
    }

    public function test_has_many_count_should_count_only_the_related_records(): void
    {
        $other = $this->createUser('Outro Admin', 'outro@example.com', 'admin');
        $this->createEvent(['title' => 'Do admin 1']);
        $this->createEvent(['title' => 'Do admin 2']);
        $this->createEvent(['title' => 'Do outro'], $other);

        $this->assertSame(2, $this->admin->events()->count());
        $this->assertSame(1, $other->events()->count());
        $this->assertSame(count($this->admin->events()->get()), $this->admin->events()->count());
    }

    public function test_enrollments_count_should_use_the_count_of_the_events_enrollments(): void
    {
        $event = $this->createEvent();
        $other = $this->createEvent(['title' => 'Outro']);
        for ($i = 1; $i <= 2; $i++) {
            $participant = $this->createUser("Participante {$i}", "p{$i}@example.com");
            (new Enrollment(['event_id' => $event->id, 'user_id' => $participant->id]))->save();
        }

        $this->assertSame(2, $event->enrollmentsCount());
        $this->assertSame(0, $other->enrollmentsCount());
        $this->assertSame(2, Enrollment::count(['event_id' => $event->id]));
        $this->assertSame(2, Enrollment::count());
    }
}
