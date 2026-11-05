<?php

namespace Tests\Integration\Controllers\Organizer;

use App\Models\Event;
use App\Models\User;
use Lib\Authentication\Auth;
use Tests\Integration\Controllers\ControllerTestCase;

class EventsControllerTest extends ControllerTestCase
{
    private const CONTROLLER = 'App\Controllers\Organizer\EventsController';

    private User $organizer;
    private User $admin;

    public function setUp(): void
    {
        parent::setUp();
        $_SESSION['flash'] = [];

        $this->admin = $this->createUser('Admin', 'admin@example.com', 'admin');
        $this->organizer = $this->createUser('Organizadora', 'organizador@example.com', 'organizer');
        Auth::login($this->organizer);
    }

    public function tearDown(): void
    {
        unset($_SESSION['flash']);
        parent::tearDown();
    }

    public function test_index_lists_only_the_organizer_events_with_organizer_links(): void
    {
        $mine = $this->createEvent($this->organizer, ['title' => 'Meu Evento']);
        $this->createEvent($this->admin, ['title' => 'Evento do Admin']);

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertStringContainsString('Meu Evento', $response);
        $this->assertStringNotContainsString('Evento do Admin', $response);
        $this->assertStringContainsString('Mostrando 1 - 1 de 1', $response);
        $this->assertStringContainsString('href="/organizer/events/new"', $response);
        $this->assertStringContainsString("href=\"/organizer/events/{$mine->id}\"", $response);
        $this->assertStringContainsString("href=\"/organizer/events/{$mine->id}/edit\"", $response);
        $this->assertStringContainsString("action=\"/organizer/events/{$mine->id}\"", $response);
        $this->assertStringNotContainsString('/admin/events', $response);
    }

    public function test_index_paginates_only_the_organizer_events(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->createEvent($this->admin, ['title' => "Alheio {$i}"]);
        }
        for ($i = 1; $i <= 6; $i++) {
            $this->createEvent($this->organizer, ['title' => "Próprio {$i}"]);
        }

        $page1 = $this->get(action: 'index', controllerName: self::CONTROLLER);
        $page2 = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['page' => 2]);

        $this->assertStringContainsString('Mostrando 1 - 5 de 6', $page1);
        $this->assertStringContainsString('/organizer/events/page/2', $page1);
        $this->assertStringContainsString('Mostrando 6 - 6 de 6', $page2);
        $this->assertStringContainsString('Próprio 6', $page2);
        $this->assertStringNotContainsString('Alheio', $page1 . $page2);
    }

    public function test_new_renders_form_posting_to_organizer_route(): void
    {
        $response = $this->get(action: 'new', controllerName: self::CONTROLLER);

        $this->assertMatchesRegularExpression('/<h1>\s*Novo Evento\s*<\/h1>/', $response);
        $this->assertStringContainsString('action="/organizer/events"', $response);
        $this->assertStringContainsString('href="/organizer/events">Cancelar', $response);
    }

    public function test_create_saves_event_owned_by_the_organizer(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['user_id' => (string) $this->admin->id])]
        );

        $event = Event::findBy(['title' => 'Oficina do Organizador']);
        $this->assertInstanceOf(Event::class, $event);
        $this->assertEquals($this->organizer->id, $event->user_id);
        $this->assertSame("Location: /organizer/events/{$event->id}", $response);
        $this->assertEquals('Evento cadastrado com sucesso!', $_SESSION['flash']['success']);
    }

    public function test_create_with_invalid_data_renders_form_again(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['title' => ''])]
        );

        $this->assertCount(0, Event::all());
        $this->assertStringContainsString('action="/organizer/events"', $response);
        $this->assertStringContainsString('não pode ser vazio!', $response);
    }

    public function test_show_edit_and_update_of_own_event(): void
    {
        $event = $this->createEvent($this->organizer, ['title' => 'Antes']);

        $show = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);
        $edit = $this->get(action: 'edit', controllerName: self::CONTROLLER, params: ['id' => $event->id]);
        $update = $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams(['title' => 'Depois'])]
        );

        $this->assertStringContainsString("href=\"/organizer/events/{$event->id}/edit\"", $show);
        $this->assertStringContainsString("action=\"/organizer/events/{$event->id}\"", $edit);
        $this->assertSame("Location: /organizer/events/{$event->id}", $update);
        $this->assertEquals('Depois', Event::findById($event->id)?->title);
    }

    public function test_destroy_of_own_event_removes_it(): void
    {
        $event = $this->createEvent($this->organizer);

        $response = $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertNull(Event::findById($event->id));
        $this->assertSame('Location: /organizer/events', $response);
        $this->assertEquals('Evento removido com sucesso!', $_SESSION['flash']['success']);
    }

    public function test_show_and_edit_of_another_user_event_redirect_to_index(): void
    {
        $event = $this->createEvent($this->admin, ['title' => 'Evento do Admin']);

        foreach (['show', 'edit'] as $action) {
            $response = $this->get(action: $action, controllerName: self::CONTROLLER, params: ['id' => $event->id]);

            $this->assertSame('Location: /organizer/events', $response, $action);
            $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger'], $action);
        }
    }

    public function test_update_and_destroy_of_another_user_event_do_not_change_it(): void
    {
        $event = $this->createEvent($this->admin, ['title' => 'Evento do Admin']);

        $update = $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams(['title' => 'Invadido'])]
        );
        $destroy = $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertSame('Location: /organizer/events', $update);
        $this->assertSame('Location: /organizer/events', $destroy);
        $this->assertEquals('Evento do Admin', Event::findById($event->id)?->title);
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

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validParams(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Oficina do Organizador',
            'description' => 'Oficina criada pelo Organizador.',
            'kind' => 'workshop',
            'modality' => 'in_person',
            'audience' => ['students'],
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Bloco A',
            'capacity' => '20',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createEvent(User $owner, array $overrides = []): Event
    {
        $params = array_merge($this->validParams(), $overrides);
        $audience = $params['audience'];
        unset($params['audience']);

        $event = new Event($params + ['user_id' => $owner->id]);
        $event->setAudienceList($audience);
        $event->save();

        return $event;
    }
}
