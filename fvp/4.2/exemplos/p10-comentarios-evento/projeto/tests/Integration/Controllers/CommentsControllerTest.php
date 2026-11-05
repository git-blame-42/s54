<?php

namespace Tests\Integration\Controllers;

use App\Models\Comment;
use App\Models\Event;
use App\Models\User;
use Lib\Authentication\Auth;

class CommentsControllerTest extends ControllerTestCase
{
    private const CONTROLLER = 'App\Controllers\CommentsController';
    private const EVENTS_CONTROLLER = 'App\Controllers\EventsController';

    private User $admin;
    private User $participant;
    private Event $event;

    public function setUp(): void
    {
        parent::setUp();
        $_SESSION['flash'] = [];

        $this->admin = $this->createUser('Admin', 'admin@example.com', 'admin');
        $this->participant = $this->createUser('Participante', 'participante@example.com', 'participant');

        $this->event = new Event([
            'user_id' => $this->admin->id,
            'title' => 'Oficina de Comentários',
            'description' => 'Descrição',
            'kind' => 'workshop',
            'modality' => 'online',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Online',
            'capacity' => 5,
        ]);
        $this->event->save();

        Auth::login($this->participant);
    }

    public function tearDown(): void
    {
        unset($_SESSION['flash']);
        parent::tearDown();
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
     * @param array<string, mixed> $params
     */
    private function comment(array $params): string
    {
        return $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['id' => $this->event->id] + $params
        );
    }

    public function test_participant_comment_is_saved_and_redirects_to_event_page(): void
    {
        $response = $this->comment(['comment' => ['body' => '  Haverá certificado?  ']]);

        $comments = $this->event->comments()->get();
        $this->assertCount(1, $comments);
        $this->assertEquals('Haverá certificado?', $comments[0]->body); // @phpstan-ignore-line
        $this->assertEquals($this->participant->id, $comments[0]->user_id); // @phpstan-ignore-line
        $this->assertSame("Location: /events/{$this->event->id}", $response);
        $this->assertEquals('Comentário publicado!', $_SESSION['flash']['success']);
    }

    public function test_admin_comment_redirects_to_admin_event_page(): void
    {
        Auth::login($this->admin);

        $response = $this->comment(['comment' => ['body' => 'Sim, haverá.']]);

        $this->assertCount(1, Comment::all());
        $this->assertSame("Location: /admin/events/{$this->event->id}", $response);
    }

    public function test_user_id_comes_from_session_not_from_form(): void
    {
        $this->comment(['comment' => ['body' => 'Oi', 'user_id' => $this->admin->id]]);

        $comment = Comment::all()[0];
        $this->assertEquals($this->participant->id, $comment->user_id);
    }

    public function test_empty_comment_is_not_saved(): void
    {
        $response = $this->comment(['comment' => ['body' => '   ']]);

        $this->assertCount(0, Comment::all());
        $this->assertSame("Location: /events/{$this->event->id}", $response);
        $this->assertEquals('Comentário não pode ser vazio!', $_SESSION['flash']['danger']);
    }

    public function test_too_long_comment_is_not_saved(): void
    {
        $this->comment(['comment' => ['body' => str_repeat('a', 501)]]);

        $this->assertCount(0, Comment::all());
        $this->assertEquals('Comentário deve ter no máximo 500 caracteres!', $_SESSION['flash']['danger']);
    }

    public function test_missing_or_array_body_is_rejected(): void
    {
        $this->comment([]);
        $this->comment(['comment' => ['body' => ['x']]]);
        $this->comment(['comment' => 'texto solto']);

        $this->assertCount(0, Comment::all());
        $this->assertEquals('Comentário não pode ser vazio!', $_SESSION['flash']['danger']);
    }

    public function test_comment_on_unknown_event_redirects_to_dashboard(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['id' => 9999, 'comment' => ['body' => 'Oi']]
        );

        $this->assertCount(0, Comment::all());
        $this->assertSame('Location: /dashboard', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
    }

    public function test_event_page_lists_comments_escaped_with_form(): void
    {
        (new Comment([
            'event_id' => $this->event->id,
            'user_id' => $this->participant->id,
            'body' => "<script>alert(1)</script>\nsegunda linha",
        ]))->save();

        $response = $this->get(
            action: 'show',
            controllerName: self::EVENTS_CONTROLLER,
            params: ['id' => $this->event->id]
        );

        $this->assertStringContainsString('Oficina de Comentários', $response);
        $this->assertStringContainsString('Comentários (1)', $response);
        $this->assertStringContainsString('Participante', $response);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;<br />', $response);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response);
        $this->assertStringContainsString("action=\"/events/{$this->event->id}/comments\"", $response);
        $this->assertStringContainsString('name="comment[body]"', $response);
        $this->assertStringNotContainsString('/edit', $response);
    }

    public function test_event_page_without_comments_shows_empty_message(): void
    {
        $response = $this->get(
            action: 'show',
            controllerName: self::EVENTS_CONTROLLER,
            params: ['id' => $this->event->id]
        );

        $this->assertStringContainsString('Nenhum comentário ainda.', $response);
    }

    public function test_event_page_with_unknown_id_redirects_to_dashboard(): void
    {
        $response = $this->get(action: 'show', controllerName: self::EVENTS_CONTROLLER, params: ['id' => 9999]);

        $this->assertSame('Location: /dashboard', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
    }

    public function test_admin_event_page_shows_comments(): void
    {
        $this->comment(['comment' => ['body' => 'Comentário visto pelo admin']]);
        Auth::login($this->admin);

        $response = $this->get(
            action: 'show',
            controllerName: 'App\Controllers\Admin\EventsController',
            params: ['id' => $this->event->id]
        );

        $this->assertStringContainsString('Comentário visto pelo admin', $response);
        $this->assertStringContainsString("action=\"/events/{$this->event->id}/comments\"", $response);
    }
}
