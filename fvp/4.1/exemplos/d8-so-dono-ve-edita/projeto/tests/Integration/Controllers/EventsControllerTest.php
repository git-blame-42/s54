<?php

namespace Tests\Integration\Controllers;

use App\Models\Event;
use App\Models\User;
use Core\Exceptions\HTTPException;
use Lib\Authentication\Auth;

class EventsControllerTest extends ControllerTestCase
{
    private const CONTROLLER = 'App\Controllers\EventsController';

    private User $user;
    private Event $event;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = new User([
            'name' => 'User 1',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $this->user->save();

        $this->event = new Event([
            'title' => 'Semana Acadêmica',
            'starts_at' => '2026-10-20 19:30:00',
            'user_id' => $this->user->id
        ]);
        $this->event->save();

        Auth::login($this->user);
    }

    public function test_index_should_list_only_the_user_events(): void
    {
        $other = new User([
            'name' => 'User 2',
            'email' => 'fulano2@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $other->save();
        (new Event(['title' => 'Evento de outro', 'starts_at' => '2026-11-01 08:00:00', 'user_id' => $other->id]))->save();

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertStringContainsString('Meus Eventos', $response);
        $this->assertStringContainsString('Semana Acadêmica', $response);
        $this->assertStringContainsString('20/10/2026 19:30', $response);
        $this->assertStringNotContainsString('Evento de outro', $response);
    }

    public function test_index_should_paginate_ten_per_page(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->user->events()->new(['title' => "Evento {$i}", 'starts_at' => '2026-11-01 08:00:00'])->save();
        }

        $page1 = $this->get(action: 'index', controllerName: self::CONTROLLER);
        $page2 = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['page' => 2]);

        $this->assertStringContainsString('Evento 9', $page1);
        $this->assertStringNotContainsString('Evento 10', $page1);
        $this->assertStringContainsString('Evento 10', $page2);
        $this->assertStringContainsString('Mostrando 11 - 11 de 11', $page2);
    }

    public function test_new_should_render_the_form(): void
    {
        $response = $this->get(action: 'new', controllerName: self::CONTROLLER);

        $this->assertMatchesRegularExpression('/<h1>\s*Novo Evento\s*<\/h1>/', $response);
        $this->assertStringContainsString('name="event[title]"', $response);
        $this->assertStringContainsString('name="event[starts_at]"', $response);
    }

    public function test_create_with_valid_params_should_save_and_redirect(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => ['title' => 'Palestra', 'starts_at' => '2026-10-21T19:00']]
        );

        $this->assertStringContainsString('Location: /events', $response);
        $this->assertCount(2, Event::all());

        $created = Event::findBy(['title' => 'Palestra']);
        $this->assertEquals($this->user->id, $created->user_id);
        $this->assertEquals('2026-10-21 19:00:00', $created->starts_at);
    }

    public function test_create_with_invalid_params_should_render_errors(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => ['title' => '', 'starts_at' => '']]
        );

        $this->assertStringNotContainsString('Location:', $response);
        $this->assertStringContainsString('não pode ser vazio!', $response);
        $this->assertStringContainsString('is-invalid', $response);
        $this->assertCount(1, Event::all());
    }

    public function test_create_should_ignore_user_id_from_params(): void
    {
        $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => ['title' => 'Palestra', 'starts_at' => '2026-10-21T19:00', 'user_id' => 999]]
        );

        $this->assertEquals($this->user->id, Event::findBy(['title' => 'Palestra'])->user_id);
    }

    public function test_show_should_render_the_event(): void
    {
        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $this->event->id]);

        $this->assertStringContainsString("Evento #{$this->event->id}", $response);
        $this->assertStringContainsString('Semana Acadêmica', $response);
        $this->assertStringContainsString('20/10/2026 19:30', $response);
    }

    public function test_show_should_respond_404_for_another_user_event(): void
    {
        $event = $this->createAnotherUserEvent();

        $this->expectException(HTTPException::class);
        $this->expectExceptionMessage('Evento não encontrado');

        $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);
    }

    public function test_edit_should_respond_404_for_another_user_event(): void
    {
        $event = $this->createAnotherUserEvent();

        $exception = $this->catchHttpException(
            fn() => $this->get(action: 'edit', controllerName: self::CONTROLLER, params: ['id' => $event->id])
        );

        $this->assertEquals(404, $exception->getStatusCode());
        $this->assertEquals('Evento não encontrado', $exception->getMessage());
    }

    public function test_update_should_respond_404_and_keep_another_user_event_intact(): void
    {
        $event = $this->createAnotherUserEvent();

        $exception = $this->catchHttpException(
            fn() => $this->put(
                action: 'update',
                controllerName: self::CONTROLLER,
                params: ['id' => $event->id, 'event' => ['title' => 'Invadido', 'starts_at' => '2030-01-01T00:00']]
            )
        );

        $this->assertEquals(404, $exception->getStatusCode());

        $intact = Event::findById($event->id);
        $this->assertEquals('De outro', $intact->title);
        $this->assertEquals('2026-11-01 08:00:00', $intact->starts_at);
        $this->assertEquals($event->user_id, $intact->user_id);
    }

    public function test_destroy_should_respond_404_and_keep_another_user_event(): void
    {
        $event = $this->createAnotherUserEvent();

        $exception = $this->catchHttpException(
            fn() => $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: ['id' => $event->id])
        );

        $this->assertEquals(404, $exception->getStatusCode());
        $this->assertNotNull(Event::findById($event->id));
        $this->assertCount(2, Event::all());
    }

    public function test_edit_should_render_the_form_with_values(): void
    {
        $response = $this->get(action: 'edit', controllerName: self::CONTROLLER, params: ['id' => $this->event->id]);

        $this->assertStringContainsString("Editar Evento #{$this->event->id}", $response);
        $this->assertStringContainsString('value="Semana Acadêmica"', $response);
        $this->assertStringContainsString('value="2026-10-20T19:30"', $response);
        $this->assertStringContainsString('name="_method" value="PUT"', $response);
    }

    public function test_update_with_valid_params_should_save_and_redirect(): void
    {
        $response = $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $this->event->id, 'event' => ['title' => 'Semana 2026', 'starts_at' => '2026-10-25T08:00']]
        );

        $this->assertStringContainsString('Location: /events', $response);

        $updated = Event::findById($this->event->id);
        $this->assertEquals('Semana 2026', $updated->title);
        $this->assertEquals('2026-10-25 08:00:00', $updated->starts_at);
    }

    public function test_update_with_invalid_params_should_render_errors(): void
    {
        $response = $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $this->event->id, 'event' => ['title' => '', 'starts_at' => '2026-10-25T08:00']]
        );

        $this->assertStringNotContainsString('Location:', $response);
        $this->assertStringContainsString('não pode ser vazio!', $response);
        $this->assertEquals('Semana Acadêmica', Event::findById($this->event->id)->title);
    }

    public function test_destroy_should_remove_and_redirect(): void
    {
        $response = $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: ['id' => $this->event->id]);

        $this->assertStringContainsString('Location: /events', $response);
        $this->assertCount(0, Event::all());
    }

    public function test_destroy_should_respond_404_for_unknown_event(): void
    {
        $this->expectException(HTTPException::class);

        $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: ['id' => 999]);
    }

    /**
     * Cria um segundo usuário com um evento próprio; o usuário logado continua sendo $this->user.
     */
    private function createAnotherUserEvent(): Event
    {
        $other = new User([
            'name' => 'User 2',
            'email' => 'fulano2@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $other->save();

        $event = new Event(['title' => 'De outro', 'starts_at' => '2026-11-01 08:00:00', 'user_id' => $other->id]);
        $event->save();

        return $event;
    }

    /**
     * Executa a ação esperando HTTPException; devolve a exceção para o teste conferir o banco depois.
     */
    private function catchHttpException(callable $action): HTTPException
    {
        try {
            $action();
        } catch (HTTPException $e) {
            return $e;
        }

        $this->fail('Era esperada uma HTTPException.');
    }
}
