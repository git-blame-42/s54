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

    public function test_enrollment_is_rejected_when_the_event_is_full(): void
    {
        $this->event->update(['capacity' => 1]);
        $other = $this->createParticipant('outro@example.com');
        (new Enrollment(['event_id' => $this->event->id, 'user_id' => $other->id]))->save();

        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);

        $this->assertFalse($enrollment->save());
        $this->assertEquals('não há vagas neste evento!', $enrollment->errors('base'));
        $this->assertCount(1, Enrollment::all());
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

    /* ------------------- confirmação de presença pelo código de check-in ------------------- */

    private function enrollParticipant(): Enrollment
    {
        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);
        $this->assertTrue($enrollment->save());

        return $enrollment;
    }

    private function checkinCode(): string
    {
        return (string) Event::findById((int) $this->event->id)?->checkin_code;
    }

    public function test_new_enrollment_should_not_be_confirmed(): void
    {
        $enrollment = $this->enrollParticipant();

        $this->assertFalse($enrollment->isConfirmed());
        $this->assertNull(Enrollment::findById((int) $enrollment->id)?->confirmed_at);
    }

    public function test_confirm_with_the_right_code_should_store_confirmed_at(): void
    {
        $enrollment = $this->enrollParticipant();

        $this->assertTrue($enrollment->confirm($this->checkinCode()));

        $this->assertTrue($enrollment->isConfirmed());
        $stored = Enrollment::findById((int) $enrollment->id);
        $this->assertNotNull($stored);
        $this->assertTrue($stored->isConfirmed());
        $this->assertSame(1, $this->event->confirmedCount());
    }

    public function test_confirm_with_a_wrong_code_should_fail_without_storing(): void
    {
        $enrollment = $this->enrollParticipant();
        $wrong = (string) ((int) $this->checkinCode() === 9999 ? 1000 : (int) $this->checkinCode() + 1);

        $this->assertFalse($enrollment->confirm($wrong));

        $this->assertEquals('código inválido!', $enrollment->errors('base'));
        $this->assertFalse(Enrollment::findById((int) $enrollment->id)?->isConfirmed());
        $this->assertSame(0, $this->event->confirmedCount());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedCodesProvider(): array
    {
        return [
            'vazio' => [''],
            'letras' => ['12a4'],
            'negativo' => ['-123'],
            'decimal' => ['12.0'],
        ];
    }

    #[DataProvider('malformedCodesProvider')]
    public function test_confirm_with_a_malformed_code_should_fail(string $code): void
    {
        $enrollment = $this->enrollParticipant();

        $this->assertFalse($enrollment->confirm($code));
        $this->assertEquals('código inválido!', $enrollment->errors('base'));
        $this->assertFalse(Enrollment::findById((int) $enrollment->id)?->isConfirmed());
    }

    public function test_confirm_twice_should_keep_the_first_date(): void
    {
        $enrollment = $this->enrollParticipant();
        $this->assertTrue($enrollment->confirm($this->checkinCode()));
        $first = Enrollment::findById((int) $enrollment->id)?->confirmed_at;

        $again = Enrollment::findById((int) $enrollment->id);
        $this->assertNotNull($again);
        $this->assertFalse($again->confirm($this->checkinCode()));

        $this->assertEquals('presença já confirmada!', $again->errors('base'));
        $this->assertSame($first, Enrollment::findById((int) $enrollment->id)?->confirmed_at);
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
