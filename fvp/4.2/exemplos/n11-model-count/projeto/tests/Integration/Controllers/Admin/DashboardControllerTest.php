<?php

namespace Tests\Integration\Controllers\Admin;

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

        $this->admin = new User([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin',
        ]);
        $this->admin->save();
        Auth::login($this->admin);
    }

    public function test_index_shows_the_total_of_events(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            (new Event([
                'user_id' => $this->admin->id,
                'title' => "Evento {$i}",
                'description' => 'Descrição.',
                'kind' => 'lecture',
                'modality' => 'online',
                'audience' => 'students',
                'starts_on' => date('Y-m-d', strtotime('+10 days')),
                'location' => 'Online',
                'capacity' => 10,
            ]))->save();
        }

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertMatchesRegularExpression('/<h1>\s*Painel Administrativo\s*<\/h1>/', $response);
        $this->assertStringContainsString('id="events_count">3</span>', $response);
    }

    public function test_index_without_events_shows_zero(): void
    {
        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertStringContainsString('id="events_count">0</span>', $response);
    }
}
