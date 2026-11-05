<?php

namespace Tests\Unit\Models\Events;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\User;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->assertEquals('o participante já está inscrito neste evento!', $duplicate->errors('base'));
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

    public function test_enrollment_should_be_rejected_when_the_event_is_full(): void
    {
        $this->event->update(['capacity' => 1]);
        $other = $this->createParticipant('outro@example.com');
        $this->assertTrue((new Enrollment(['event_id' => $this->event->id, 'user_id' => $other->id]))->save());

        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);

        $this->assertFalse($enrollment->save());
        $this->assertEquals('não há vagas neste evento!', $enrollment->errors('base'));
        $this->assertCount(1, Enrollment::all());
    }

    public function test_enrollment_should_take_the_last_available_spot(): void
    {
        $this->event->update(['capacity' => 1]);

        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);

        $this->assertTrue($enrollment->save());
        $this->assertSame(0, $this->event->availableSpots());
    }

    public function test_only_participants_can_be_enrolled(): void
    {
        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);

        $this->assertFalse($enrollment->save());
        $this->assertEquals('somente Participantes podem ser inscritos!', $enrollment->errors('base'));
        $this->assertCount(0, Enrollment::all());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidUserIds(): array
    {
        return [
            'inexistente' => [9999],
            'texto' => ['abc'],
            'número com sufixo' => ['1abc'],
        ];
    }

    #[DataProvider('invalidUserIds')]
    public function test_user_must_exist(mixed $userId): void
    {
        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $userId]);

        $this->assertFalse($enrollment->save());
        $this->assertEquals('o usuário informado não existe!', $enrollment->errors('base'));
        $this->assertCount(0, Enrollment::all());
    }

    public function test_event_must_exist(): void
    {
        $enrollment = new Enrollment(['event_id' => 9999, 'user_id' => $this->participant->id]);

        $this->assertFalse($enrollment->save());
        $this->assertEquals('o evento informado não existe!', $enrollment->errors('base'));
    }

    public function test_event_and_user_are_required(): void
    {
        $withoutUser = new Enrollment(['event_id' => $this->event->id, 'user_id' => '']);
        $withoutEvent = new Enrollment(['event_id' => null, 'user_id' => $this->participant->id]);

        $this->assertFalse($withoutUser->save());
        $this->assertEquals('não pode ser vazio!', $withoutUser->errors('user_id'));
        $this->assertNull($withoutUser->errors('base'));
        $this->assertFalse($withoutEvent->save());
        $this->assertEquals('não pode ser vazio!', $withoutEvent->errors('event_id'));
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
