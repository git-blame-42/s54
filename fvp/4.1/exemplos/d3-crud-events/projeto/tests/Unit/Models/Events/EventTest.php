<?php

namespace Tests\Unit\Models\Events;

use App\Models\Event;
use App\Models\User;
use Tests\TestCase;

class EventTest extends TestCase
{
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
    }

    public function test_should_create_new_event(): void
    {
        $this->assertCount(1, Event::all());
        $this->assertEquals('Semana Acadêmica', Event::all()[0]->title);
    }

    public function test_find_by_id_should_return_the_event(): void
    {
        $found = Event::findById($this->event->id);

        $this->assertEquals('Semana Acadêmica', $found->title);
        $this->assertEquals('2026-10-20 19:30:00', $found->starts_at);
        $this->assertEquals($this->user->id, $found->user_id);
    }

    public function test_find_by_id_should_return_null(): void
    {
        $this->assertNull(Event::findById(999));
    }

    public function test_errors_should_return_empty_title_error(): void
    {
        $event = new Event(['starts_at' => '2026-10-20 19:30:00', 'user_id' => $this->user->id]);

        $this->assertFalse($event->isValid());
        $this->assertFalse($event->save());
        $this->assertEquals('não pode ser vazio!', $event->errors('title'));
        $this->assertNull($event->errors('starts_at'));
    }

    public function test_errors_should_return_empty_starts_at_error(): void
    {
        $event = new Event(['title' => 'Palestra', 'starts_at' => '', 'user_id' => $this->user->id]);

        $this->assertFalse($event->isValid());
        $this->assertEquals('não pode ser vazio!', $event->errors('starts_at'));
        $this->assertCount(1, Event::all());
    }

    public function test_save_should_update_existing_event(): void
    {
        $this->event->title = 'Semana Acadêmica 2026';
        $this->assertTrue($this->event->save());

        $this->assertEquals('Semana Acadêmica 2026', Event::findById($this->event->id)->title);
        $this->assertCount(1, Event::all());
    }

    public function test_destroy_should_remove_the_event(): void
    {
        $this->assertTrue($this->event->destroy());
        $this->assertCount(0, Event::all());
    }

    public function test_user_should_return_the_owner(): void
    {
        $this->assertEquals($this->user->id, $this->event->user->id);
        $this->assertEquals('fulano@example.com', $this->event->user->email);
    }

    public function test_where_should_filter_by_user(): void
    {
        $other = new User([
            'name' => 'User 2',
            'email' => 'fulano2@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $other->save();
        (new Event(['title' => 'Outro', 'starts_at' => '2026-11-01 08:00:00', 'user_id' => $other->id]))->save();

        $mine = Event::where(['user_id' => $this->user->id]);

        $this->assertCount(2, Event::all());
        $this->assertCount(1, $mine);
        $this->assertEquals($this->event->id, $mine[0]->id);
    }

    public function test_starts_at_formatted_should_use_brazilian_format(): void
    {
        $this->assertEquals('20/10/2026 19:30', $this->event->startsAtFormatted());
        $this->assertEquals('2026-10-20T19:30', $this->event->startsAtForInput());
    }

    public function test_starts_at_formatted_should_be_empty_without_date(): void
    {
        $event = new Event(['title' => 'Sem data']);

        $this->assertEquals('', $event->startsAtFormatted());
        $this->assertEquals('', $event->startsAtForInput());
    }

    public function test_destroying_user_should_cascade_events(): void
    {
        $this->user->destroy();

        $this->assertCount(0, Event::all());
    }
}
