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

    public function test_index_shows_zero_totals_without_records(): void
    {
        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertMatchesRegularExpression('/<h1>\s*Painel Administrativo\s*<\/h1>/', $response);
        $this->assertTotal('total_events', 0, $response);
        $this->assertTotal('total_enrollments', 0, $response);
    }

    public function test_index_shows_totals_of_events_and_enrollments(): void
    {
        $first = $this->createEvent('Evento A');
        $second = $this->createEvent('Evento B');
        $this->createEvent('Evento C');

        $ana = $this->createUser('Ana', 'ana@example.com', 'participant');
        $bia = $this->createUser('Bia', 'bia@example.com', 'participant');
        foreach ([[$first, $ana], [$first, $bia], [$second, $ana]] as [$event, $user]) {
            (new Enrollment(['event_id' => $event->id, 'user_id' => $user->id]))->save();
        }

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertTotal('total_events', 3, $response);
        $this->assertTotal('total_enrollments', 3, $response);
    }

    private function assertTotal(string $id, int $expected, string $response): void
    {
        $this->assertMatchesRegularExpression(
            '/id="' . $id . '".*?<p class="card-title[^"]*">' . $expected . '<\/p>/s',
            $response
        );
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

    private function createEvent(string $title): Event
    {
        $event = new Event([
            'user_id' => $this->admin->id,
            'title' => $title,
            'description' => 'Descrição.',
            'kind' => 'lecture',
            'modality' => 'online',
            'starts_on' => date('Y-m-d', strtotime('+5 days')),
            'location' => 'Sala 1',
            'capacity' => 10,
        ]);
        $event->setAudienceList(['students']);
        $event->save();

        return $event;
    }
}
