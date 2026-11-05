<?php

namespace Tests\Integration\Controllers\Admin;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\User;
use Lib\Authentication\Auth;
use Tests\Integration\Controllers\ControllerTestCase;

class DashboardControllerTest extends ControllerTestCase
{
    private const CONTROLLER = 'App\Controllers\Admin\DashboardController';

    private User $admin;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUser('Admin', 'admin@example.com', 'admin');
        Auth::login($this->admin);
    }

    private function createUser(string $name, string $email, string $role): User
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

    private function createEvent(string $title, string $kind, int $daysAhead): Event
    {
        $event = new Event([
            'user_id' => $this->admin->id,
            'title' => $title,
            'description' => 'Descrição.',
            'kind' => $kind,
            'modality' => 'online',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime("+{$daysAhead} days")),
            'location' => 'Online',
            'capacity' => 10,
        ]);
        $event->save();

        return $event;
    }

    public function test_index_shows_totals_counts_by_kind_and_next_three_events(): void
    {
        $this->createEvent('Oficina Distante', 'workshop', 40);
        $lecture = $this->createEvent('Palestra Amanhã', 'lecture', 1);
        $this->createEvent('Palestra Semana', 'lecture', 7);
        $this->createEvent('Minicurso Mês', 'short_course', 30);
        $participant = $this->createUser('Participante', 'participante@example.com', 'participant');
        (new Enrollment(['event_id' => $lecture->id, 'user_id' => $participant->id]))->save();

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertMatchesRegularExpression('/<h1>\s*Painel Administrativo\s*<\/h1>/', $response);
        $this->assertMatchesRegularExpression('/id="total_events">4</', $response);
        $this->assertMatchesRegularExpression('/id="total_enrollments">1</', $response);
        $this->assertMatchesRegularExpression('/<td>Palestra<\/td>\s*<td class="text-end">2<\/td>/', $response);
        $this->assertMatchesRegularExpression('/<td>Oficina<\/td>\s*<td class="text-end">1<\/td>/', $response);
        $this->assertMatchesRegularExpression('/<td>Semana acadêmica<\/td>\s*<td class="text-end">0<\/td>/', $response);
        $this->assertMatchesRegularExpression('/Palestra Amanhã.*Palestra Semana.*Minicurso Mês/s', $response);
        $this->assertStringNotContainsString('Oficina Distante', $this->upcomingTable($response));
        $this->assertStringContainsString("/admin/events/{$lecture->id}", $response);
    }

    public function test_index_without_events_shows_zeros(): void
    {
        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertMatchesRegularExpression('/id="total_events">0</', $response);
        $this->assertMatchesRegularExpression('/id="total_enrollments">0</', $response);
        $this->assertStringContainsString('Nenhum evento agendado.', $response);
    }

    private function upcomingTable(string $response): string
    {
        $start = strpos($response, 'id="upcoming_events"');
        $this->assertNotFalse($start);

        return substr($response, $start, (int) strpos($response, '</table>', $start) - $start);
    }
}
