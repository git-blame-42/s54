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

    /* ------------------- número do comprovante ------------------- */

    public function test_save_should_generate_a_receipt_code_between_1000_and_9999(): void
    {
        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);
        $this->assertNull($enrollment->receipt_code);

        $this->assertTrue($enrollment->save());

        $code = (int) Enrollment::findById($enrollment->id)->receipt_code;
        $this->assertGreaterThanOrEqual(Enrollment::RECEIPT_CODE_MIN, $code);
        $this->assertLessThanOrEqual(Enrollment::RECEIPT_CODE_MAX, $code);
    }

    public function test_receipt_code_should_not_change_when_saving_again(): void
    {
        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);
        $enrollment->save();
        $code = $enrollment->receipt_code;

        $this->assertTrue($enrollment->save());

        $this->assertEquals($code, $enrollment->receipt_code);
        $this->assertEquals($code, Enrollment::findById($enrollment->id)->receipt_code);
    }

    public function test_receipt_codes_should_be_unique_within_the_event(): void
    {
        $codes = [];
        for ($i = 1; $i <= 5; $i++) {
            $enrollment = new Enrollment([
                'event_id' => $this->event->id,
                'user_id' => $this->createParticipant("p{$i}@example.com")->id,
            ]);
            $this->assertTrue($enrollment->save());
            $codes[] = (int) $enrollment->receipt_code;
        }

        $this->assertCount(5, array_unique($codes));
    }

    public function test_a_repeated_receipt_code_in_the_same_event_is_rejected(): void
    {
        $first = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);
        $first->save();

        $repeated = new Enrollment([
            'event_id' => $this->event->id,
            'user_id' => $this->createParticipant('outro@example.com')->id,
            'receipt_code' => $first->receipt_code,
        ]);

        $this->assertFalse($repeated->save());
        $this->assertEquals('já existe neste evento!', $repeated->errors('receipt_code'));
    }

    public function test_the_same_receipt_code_is_allowed_in_another_event(): void
    {
        $first = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);
        $first->save();

        $otherEvent = new Event(array_merge($this->eventParams(), ['title' => 'Outra Oficina']));
        $otherEvent->save();
        $second = new Enrollment([
            'event_id' => $otherEvent->id,
            'user_id' => $this->participant->id,
            'receipt_code' => $first->receipt_code,
        ]);

        $this->assertTrue($second->save());
    }

    public function test_receipt_code_out_of_range_is_rejected(): void
    {
        foreach ([999, 10000, 'abc'] as $code) {
            $enrollment = new Enrollment([
                'event_id' => $this->event->id,
                'user_id' => $this->participant->id,
                'receipt_code' => $code,
            ]);

            $this->assertFalse($enrollment->save(), (string) $code);
            $this->assertEquals('deve estar entre 1000 e 9999!', $enrollment->errors('receipt_code'));
        }
    }

    public function test_invalid_event_id_does_not_generate_a_receipt_code(): void
    {
        $enrollment = new Enrollment(['event_id' => "{$this->event->id}abc", 'user_id' => $this->participant->id]);

        $this->assertFalse($enrollment->save());
        $this->assertNull($enrollment->receipt_code);
        $this->assertCount(0, Enrollment::all());
    }

    public function test_new_receipt_code_skips_codes_already_used_in_the_event(): void
    {
        $first = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);
        $first->save();

        for ($i = 0; $i < 50; $i++) {
            $code = Enrollment::newReceiptCode($this->event->id);
            $this->assertNotEquals((int) $first->receipt_code, $code);
            $this->assertGreaterThanOrEqual(Enrollment::RECEIPT_CODE_MIN, $code);
            $this->assertLessThanOrEqual(Enrollment::RECEIPT_CODE_MAX, $code);
        }
    }

    public function test_user_enrollment_in_and_event_enrollments_return_the_receipt(): void
    {
        $this->assertNull($this->participant->enrollmentIn($this->event));
        $this->assertSame([], $this->event->enrollments());

        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);
        $enrollment->save();

        $this->assertEquals($enrollment->receipt_code, $this->participant->enrollmentIn($this->event)?->receipt_code);
        $this->assertCount(1, $this->event->enrollments());
        $this->assertEquals($enrollment->receipt_code, $this->event->enrollments()[0]->receipt_code);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventParams(): array
    {
        return [
            'user_id' => $this->admin->id,
            'title' => 'Oficina',
            'description' => 'Descrição',
            'kind' => 'workshop',
            'modality' => 'online',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Online',
            'capacity' => 5,
        ];
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
