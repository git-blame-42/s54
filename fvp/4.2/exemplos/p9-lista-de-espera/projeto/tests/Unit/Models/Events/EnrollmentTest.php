<?php

namespace Tests\Unit\Models\Events;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\User;
use PDOException;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    private User $admin;
    private User $participant;
    private Event $event;

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

        $this->participant = new User([
            'name' => 'Participante',
            'email' => 'participante@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);
        $this->participant->save();

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

    public function test_save_should_create_the_enrollment(): void
    {
        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);

        $this->assertTrue($enrollment->save());
        $this->assertCount(1, Enrollment::all());
        $this->assertEquals('event_user', Enrollment::table());
    }

    public function test_enrollment_should_reference_its_event_and_user(): void
    {
        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);
        $enrollment->save();

        $this->assertEquals($this->event->id, $enrollment->event()->get()->id);
        $this->assertEquals($this->participant->id, $enrollment->user()->get()->id);
    }

    public function test_the_same_participant_cannot_enroll_twice_in_an_event(): void
    {
        $params = ['event_id' => $this->event->id, 'user_id' => $this->participant->id];
        $this->assertTrue((new Enrollment($params))->save());

        $duplicate = new Enrollment($params);

        $this->assertFalse($duplicate->save());
        $this->assertEquals('você já está inscrito neste evento!', $duplicate->errors('base'));
        $this->assertCount(1, Enrollment::all());
    }

    public function test_the_unique_index_still_blocks_duplicates_without_validation(): void
    {
        $params = ['event_id' => $this->event->id, 'user_id' => $this->participant->id];
        (new Enrollment($params))->save();

        $this->expectException(PDOException::class);

        $duplicate = new class ($params) extends Enrollment {
            public function validates(): void
            {
            }
        };
        $duplicate->save();
    }

    public function test_new_enrollment_should_be_confirmed_by_default(): void
    {
        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);

        $this->assertTrue($enrollment->save());
        $stored = Enrollment::findBy(['id' => $enrollment->id]);
        $this->assertNotNull($stored);
        $this->assertEquals('confirmed', $stored->status);
        $this->assertFalse($stored->isWaiting());
        $this->assertSame(0, $stored->waitingPosition());
    }

    public function test_enrollment_in_a_full_event_should_go_to_the_waiting_list(): void
    {
        $this->event->update(['capacity' => 1]);
        $other = $this->createParticipant('outro@example.com');
        (new Enrollment(['event_id' => $this->event->id, 'user_id' => $other->id]))->save();

        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);

        $this->assertTrue($enrollment->save());
        $stored = Enrollment::findBy(['id' => $enrollment->id]);
        $this->assertNotNull($stored);
        $this->assertEquals('waiting', $stored->status);
        $this->assertSame(1, $stored->waitingPosition());
        $this->assertSame(1, $this->event->enrollmentsCount());
        $this->assertSame(1, $this->event->waitingCount());
        $this->assertSame(0, $this->event->availableSpots());
    }

    public function test_waiting_positions_follow_arrival_order(): void
    {
        $this->event->update(['capacity' => 1]);
        (new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]))->save();
        $second = $this->enrollNew('segundo@example.com');
        $third = $this->enrollNew('terceiro@example.com');

        $this->assertSame(1, $second->waitingPosition());
        $this->assertSame(2, $third->waitingPosition());
        $this->assertEquals([$second->id, $third->id], array_map(fn($e) => $e->id, $this->event->waitingList()));
    }

    public function test_destroying_a_confirmed_enrollment_should_promote_the_first_waiting(): void
    {
        $this->event->update(['capacity' => 1]);
        $confirmed = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);
        $confirmed->save();
        $second = $this->enrollNew('segundo@example.com');
        $third = $this->enrollNew('terceiro@example.com');

        $this->assertTrue($confirmed->destroy());

        $this->assertEquals('confirmed', Enrollment::findBy(['id' => $second->id])?->status);
        $this->assertEquals('waiting', Enrollment::findBy(['id' => $third->id])?->status);
        $this->assertSame(1, Enrollment::findBy(['id' => $third->id])?->waitingPosition());
        $this->assertSame(1, $this->event->enrollmentsCount());
        $this->assertSame(1, $this->event->waitingCount());
    }

    public function test_destroying_a_waiting_enrollment_should_not_promote_anyone(): void
    {
        $this->event->update(['capacity' => 1]);
        (new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]))->save();
        $second = $this->enrollNew('segundo@example.com');
        $third = $this->enrollNew('terceiro@example.com');

        $this->assertTrue($second->destroy());

        $this->assertEquals('waiting', Enrollment::findBy(['id' => $third->id])?->status);
        $this->assertSame(1, $this->event->enrollmentsCount());
        $this->assertSame(1, $this->event->waitingCount());
    }

    public function test_promote_first_waiting_should_do_nothing_without_spots_or_queue(): void
    {
        $this->assertFalse($this->event->promoteFirstWaiting());

        $this->event->update(['capacity' => 1]);
        (new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]))->save();
        $waiting = $this->enrollNew('segundo@example.com');

        $this->assertFalse($this->event->promoteFirstWaiting());
        $this->assertEquals('waiting', Enrollment::findBy(['id' => $waiting->id])?->status);
    }

    public function test_invalid_status_should_be_rejected(): void
    {
        $enrollment = new Enrollment([
            'event_id' => $this->event->id,
            'user_id' => $this->participant->id,
            'status' => 'cancelled',
        ]);

        $this->assertFalse($enrollment->save());
        $this->assertNotNull($enrollment->errors('status'));
    }

    public function test_safe_destroy_should_count_waiting_enrollments_too(): void
    {
        $this->event->update(['capacity' => 1]);
        (new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]))->save();
        $this->enrollNew('segundo@example.com');

        $this->assertFalse($this->event->safeDestroy());
        $this->assertEquals(
            'não é possível remover um evento com inscrições (2)!',
            $this->event->errors('base')
        );
    }

    private function enrollNew(string $email): Enrollment
    {
        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->createParticipant($email)->id]);
        $enrollment->save();

        return $enrollment;
    }

    public function test_enrollment_takes_the_last_available_spot(): void
    {
        $this->event->update(['capacity' => 1]);

        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);

        $this->assertTrue($enrollment->save());
        $this->assertSame(0, $this->event->availableSpots());
    }

    public function test_enrollment_requires_an_existing_event_and_a_user(): void
    {
        $unknownEvent = new Enrollment(['event_id' => 9999, 'user_id' => $this->participant->id]);
        $withoutUser = new Enrollment(['event_id' => $this->event->id, 'user_id' => null]);

        $this->assertFalse($unknownEvent->save());
        $this->assertEquals('evento não encontrado!', $unknownEvent->errors('event_id'));
        $this->assertFalse($withoutUser->save());
        $this->assertNotNull($withoutUser->errors('user_id'));
        $this->assertCount(0, Enrollment::all());
    }

    private function createParticipant(string $email): User
    {
        $user = new User([
            'name' => 'Outro Participante',
            'email' => $email,
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);
        $user->save();

        return $user;
    }
}
