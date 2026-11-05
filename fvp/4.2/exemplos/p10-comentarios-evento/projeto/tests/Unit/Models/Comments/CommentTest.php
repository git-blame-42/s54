<?php

namespace Tests\Unit\Models\Comments;

use App\Models\Comment;
use App\Models\Event;
use App\Models\User;
use Tests\TestCase;

class CommentTest extends TestCase
{
    private User $admin;
    private User $participant;
    private Event $event;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUser('Administrador', 'admin@example.com', 'admin');
        $this->participant = $this->createUser('Participante', 'participante@example.com', 'participant');

        $this->event = new Event([
            'user_id' => $this->admin->id,
            'title' => 'Oficina',
            'description' => 'Descrição',
            'kind' => 'workshop',
            'modality' => 'online',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Online',
            'capacity' => 5,
        ]);
        $this->event->save();
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

    private function newComment(string $body = 'Haverá certificado?'): Comment
    {
        return new Comment([
            'event_id' => $this->event->id,
            'user_id' => $this->participant->id,
            'body' => $body,
        ]);
    }

    public function test_valid_comment_should_be_saved(): void
    {
        $comment = $this->newComment();

        $this->assertTrue($comment->save());
        $this->assertCount(1, Comment::all());
        $this->assertEquals('comments', Comment::table());
    }

    public function test_created_at_should_be_filled_on_new_comment(): void
    {
        $comment = $this->newComment();
        $comment->save();

        $stored = Comment::findById($comment->id);
        $this->assertNotNull($stored);
        $this->assertNotEmpty($stored->created_at);
        $this->assertNotFalse(strtotime($stored->created_at));
    }

    public function test_empty_body_should_be_invalid(): void
    {
        $comment = $this->newComment('');

        $this->assertFalse($comment->save());
        $this->assertEquals('não pode ser vazio!', $comment->errors('body'));
        $this->assertCount(0, Comment::all());
    }

    public function test_body_with_500_characters_should_be_valid(): void
    {
        $this->assertTrue($this->newComment(str_repeat('a', 500))->isValid());
    }

    public function test_body_with_501_characters_should_be_invalid(): void
    {
        $comment = $this->newComment(str_repeat('a', 501));

        $this->assertFalse($comment->isValid());
        $this->assertEquals('deve ter no máximo 500 caracteres!', $comment->errors('body'));
    }

    public function test_event_and_user_should_be_required(): void
    {
        $comment = new Comment(['body' => 'Sem dono']);

        $this->assertFalse($comment->isValid());
        $this->assertNotNull($comment->errors('event_id'));
        $this->assertNotNull($comment->errors('user_id'));
    }

    public function test_comment_should_belong_to_event_and_user(): void
    {
        $comment = $this->newComment();
        $comment->save();

        $this->assertEquals($this->event->id, $comment->event()->get()->id);
        $this->assertEquals($this->participant->id, $comment->user()->get()->id);
        $this->assertEquals('Participante', $comment->authorName());
    }

    public function test_event_should_have_many_comments(): void
    {
        $this->newComment('Primeiro')->save();
        $this->newComment('Segundo')->save();

        $comments = $this->event->comments()->get();

        $this->assertCount(2, $comments);
        $this->assertEquals('Primeiro', $comments[0]->body); // @phpstan-ignore-line
        $this->assertEquals('Segundo', $comments[1]->body); // @phpstan-ignore-line
    }

    public function test_safe_destroy_of_event_should_remove_its_comments(): void
    {
        $this->newComment()->save();

        $this->assertTrue($this->event->safeDestroy());
        $this->assertCount(0, Comment::all());
    }
}
