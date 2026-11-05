<?php

namespace Tests\Unit\Models\Events;

use App\Models\Event;
use App\Models\User;
use Lib\CodeCheck;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Validade e limite de tentativas do código de confirmação, testados no model, sem controller.
 * O instante entra como parâmetro ($now, epoch): nada de sleep.
 */
class EventConfirmationCodeTest extends TestCase
{
    private const T = 1_000_000;

    private Event $event;
    private int $code;

    public function setUp(): void
    {
        parent::setUp();

        $admin = new User([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin',
        ]);
        $admin->save();

        $this->event = new Event([
            'user_id' => $admin->id,
            'title' => 'Semana Acadêmica',
            'description' => 'Palestras e minicursos.',
            'kind' => 'lecture',
            'modality' => 'in_person',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+30 days')),
            'location' => 'Auditório Central',
            'capacity' => 10,
        ]);
        $this->assertTrue($this->event->save());

        $this->code = $this->event->issueConfirmationCode(self::T);
    }

    private function wrongCode(): string
    {
        return $this->code === 1000 ? '1001' : '1000';
    }

    private function storedAttempts(): mixed
    {
        return Event::findById($this->event->id)->confirmation_code_attempts;
    }

    /* ------------------- geração ------------------- */

    public function test_new_event_should_start_with_the_current_time_and_no_attempts(): void
    {
        $before = time();
        $event = new Event();

        $this->assertGreaterThanOrEqual($before, $event->confirmation_code_issued_at);
        $this->assertLessThanOrEqual(time(), $event->confirmation_code_issued_at);
        $this->assertSame(0, $event->confirmation_code_attempts);
    }

    public function test_issue_should_store_the_code_the_instant_and_zero_attempts(): void
    {
        $found = Event::findById($this->event->id);

        $this->assertSame($this->code, $found->confirmation_code);
        $this->assertGreaterThanOrEqual(Event::CODE_MIN, $this->code);
        $this->assertLessThanOrEqual(Event::CODE_MAX, $this->code);
        $this->assertSame(self::T, $found->confirmation_code_issued_at);
        $this->assertSame(0, $found->confirmation_code_attempts);
    }

    /* ------------------- validade ------------------- */

    public function test_right_code_at_the_last_second_should_be_ok(): void
    {
        $this->assertSame(
            CodeCheck::Ok,
            $this->event->checkConfirmationCode((string) $this->code, self::T + Event::CODE_TTL_SECONDS)
        );
    }

    public function test_right_code_one_second_later_should_be_expired(): void
    {
        $this->assertSame(
            CodeCheck::Expired,
            $this->event->checkConfirmationCode((string) $this->code, self::T + Event::CODE_TTL_SECONDS + 1)
        );
        $this->assertSame(0, $this->storedAttempts());
    }

    public function test_right_code_should_be_used_only_once(): void
    {
        $this->assertSame(CodeCheck::Ok, $this->event->checkConfirmationCode((string) $this->code, self::T + 1));
        $this->assertSame(CodeCheck::Expired, $this->event->checkConfirmationCode((string) $this->code, self::T + 2));
    }

    /* ------------------- tentativas ------------------- */

    public function test_third_wrong_code_should_exhaust_the_attempts(): void
    {
        $this->assertSame(CodeCheck::Wrong, $this->event->checkConfirmationCode($this->wrongCode(), self::T + 1));
        $this->assertSame(CodeCheck::Wrong, $this->event->checkConfirmationCode($this->wrongCode(), self::T + 2));
        $this->assertSame(CodeCheck::Exhausted, $this->event->checkConfirmationCode($this->wrongCode(), self::T + 3));
        $this->assertSame(3, $this->storedAttempts());

        $this->assertSame(CodeCheck::Exhausted, $this->event->checkConfirmationCode((string) $this->code, self::T + 4));
        $this->assertSame(3, $this->storedAttempts());
    }

    public function test_right_code_after_two_wrong_ones_should_be_ok(): void
    {
        $this->event->checkConfirmationCode($this->wrongCode(), self::T + 1);
        $this->event->checkConfirmationCode($this->wrongCode(), self::T + 2);

        $this->assertSame(CodeCheck::Ok, $this->event->checkConfirmationCode((string) $this->code, self::T + 3));
    }

    public function test_attempts_should_survive_reloading_the_event(): void
    {
        $this->event->checkConfirmationCode($this->wrongCode(), self::T + 1);
        $this->event->checkConfirmationCode($this->wrongCode(), self::T + 2);

        $found = Event::findById($this->event->id);
        $this->assertSame(CodeCheck::Exhausted, $found->checkConfirmationCode($this->wrongCode(), self::T + 3));
    }

    public function test_exhausted_should_win_over_expired(): void
    {
        $this->event->update(['confirmation_code_attempts' => Event::CODE_MAX_ATTEMPTS]);

        $this->assertSame(CodeCheck::Exhausted, $this->event->checkConfirmationCode((string) $this->code, self::T + 999));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function malformedCodes(): array
    {
        return [
            'letras' => ['12a'],
            'espaço' => [' 1234'],
            'vazio' => [''],
            'null' => [null],
            'array' => [['1234']],
        ];
    }

    #[DataProvider('malformedCodes')]
    public function test_malformed_code_should_be_wrong_and_count_an_attempt(mixed $given): void
    {
        $this->assertSame(CodeCheck::Wrong, $this->event->checkConfirmationCode($given, self::T + 1));
        $this->assertSame(1, $this->storedAttempts());
    }

    public function test_updating_the_event_should_keep_the_attempts_and_the_instant(): void
    {
        $this->event->checkConfirmationCode($this->wrongCode(), self::T + 1);

        $found = Event::findById($this->event->id);
        $found->title = 'Outro título';
        $this->assertTrue($found->save());

        $found = Event::findById($this->event->id);
        $this->assertSame(1, $found->confirmation_code_attempts);
        $this->assertSame(self::T, $found->confirmation_code_issued_at);
        $this->assertSame($this->code, $found->confirmation_code);
    }

    /* ------------------- código vivo ------------------- */

    public function test_current_should_keep_a_live_code_and_its_attempts(): void
    {
        $this->event->checkConfirmationCode($this->wrongCode(), self::T + 1);

        $this->assertSame($this->code, $this->event->currentConfirmationCode(self::T + 2));

        $found = Event::findById($this->event->id);
        $this->assertSame(1, $found->confirmation_code_attempts);
        $this->assertSame(self::T, $found->confirmation_code_issued_at);
    }

    public function test_current_after_exhausted_should_issue_a_new_entry(): void
    {
        for ($i = 1; $i <= Event::CODE_MAX_ATTEMPTS; $i++) {
            $this->event->checkConfirmationCode($this->wrongCode(), self::T + $i);
        }

        $code = $this->event->currentConfirmationCode(self::T + 10);

        $found = Event::findById($this->event->id);
        $this->assertSame($code, $found->confirmation_code);
        $this->assertSame(0, $found->confirmation_code_attempts);
        $this->assertSame(self::T + 10, $found->confirmation_code_issued_at);
        $this->assertSame(CodeCheck::Ok, $found->checkConfirmationCode((string) $code, self::T + 11));
    }

    public function test_current_after_expired_should_issue_a_new_entry(): void
    {
        $now = self::T + Event::CODE_TTL_SECONDS + 1;

        $code = $this->event->currentConfirmationCode($now);

        $this->assertSame($now, Event::findById($this->event->id)->confirmation_code_issued_at);
        $this->assertSame(CodeCheck::Ok, $this->event->checkConfirmationCode((string) $code, $now + 60));
    }

    /* ------------------- mensagens ------------------- */

    public function test_each_result_should_have_its_own_message(): void
    {
        $messages = array_map(fn(CodeCheck $result) => $result->message(), CodeCheck::cases());

        $this->assertCount(4, array_unique($messages));
        $this->assertSame('Código incorreto! Tente de novo.', CodeCheck::Wrong->message());
        $this->assertSame('Código expirado! Um novo foi gerado.', CodeCheck::Expired->message());
        $this->assertSame('Tentativas esgotadas! Um novo código foi gerado.', CodeCheck::Exhausted->message());
    }
}
