<?php

namespace Tests\Unit\Models\Events;

use App\Models\EventKind;
use Core\Constants\Constants;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ValueError;

class EventKindTest extends TestCase
{
    public function test_label_should_return_the_portuguese_text(): void
    {
        $this->assertSame('Palestra', EventKind::Lecture->label());
        $this->assertSame('Oficina', EventKind::Workshop->label());
        $this->assertSame('Minicurso', EventKind::ShortCourse->label());
        $this->assertSame('Semana acadêmica', EventKind::AcademicWeek->label());
    }

    public function test_options_should_map_value_to_label_in_case_order(): void
    {
        $this->assertSame([
            'lecture' => 'Palestra',
            'workshop' => 'Oficina',
            'short_course' => 'Minicurso',
            'academic_week' => 'Semana acadêmica',
        ], EventKind::options());
    }

    public function test_try_from_should_return_the_case_for_a_known_value(): void
    {
        $this->assertSame(EventKind::Workshop, EventKind::tryFrom('workshop'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownValues(): array
    {
        return [
            'fora do enum' => ['concert'],
            'vazio' => [''],
            'nome do case' => ['Workshop'],
            'rótulo' => ['Oficina'],
        ];
    }

    #[DataProvider('unknownValues')]
    public function test_try_from_should_return_null_for_unknown_value(string $value): void
    {
        $this->assertNull(EventKind::tryFrom($value));
    }

    public function test_from_should_throw_for_unknown_value(): void
    {
        $this->expectException(ValueError::class);

        EventKind::from('concert');
    }

    public function test_cases_should_match_the_schema_enum(): void
    {
        $schema = (string) file_get_contents(Constants::rootPath()->join('database/schema.sql'));
        $this->assertSame(1, preg_match("/\\bkind\\s+ENUM\\(([^)]*)\\)/", $schema, $matches));

        $values = array_map(fn(string $value) => trim($value, " '"), explode(',', $matches[1]));

        $this->assertSame($values, array_keys(EventKind::options()));
    }
}
