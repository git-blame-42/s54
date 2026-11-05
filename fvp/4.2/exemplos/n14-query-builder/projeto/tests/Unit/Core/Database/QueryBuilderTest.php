<?php

namespace Tests\Unit\Core\Database;

use App\Models\Event;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Só monta o SQL (toSql/bindings): não toca no banco.
 */
class QueryBuilderTest extends TestCase
{
    private const SELECT = 'SELECT id, user_id, title, description, kind, modality, audience, starts_on, '
        . 'location, capacity FROM events';

    public function test_query_without_where_and_order_should_select_every_column(): void
    {
        $this->assertSame(self::SELECT, Event::query()->toSql());
        $this->assertSame([], Event::query()->bindings());
    }

    public function test_where_and_order_by_should_build_sql_with_numbered_placeholders(): void
    {
        $query = Event::query()
            ->where('kind', 'lecture')
            ->where('starts_on', '>=', '2026-11-05')
            ->orderBy('starts_on')
            ->orderBy('id', 'DESC');

        $this->assertSame(
            self::SELECT . ' WHERE kind = :p0 AND starts_on >= :p1 ORDER BY starts_on ASC, id DESC',
            $query->toSql()
        );
        $this->assertSame([':p0' => 'lecture', ':p1' => '2026-11-05'], $query->bindings());
    }

    public function test_same_column_twice_should_use_different_placeholders(): void
    {
        $query = Event::query()
            ->where('starts_on', '>=', '2026-11-01')
            ->where('starts_on', '<=', '2026-11-30');

        $this->assertSame('starts_on >= :p0 AND starts_on <= :p1', $query->whereSql());
        $this->assertSame([':p0' => '2026-11-01', ':p1' => '2026-11-30'], $query->bindings());
    }

    public function test_operator_and_direction_should_be_case_insensitive(): void
    {
        $query = Event::query()->where('title', 'like', '%Oficina%')->orderBy('title', 'desc');

        $this->assertSame('title LIKE :p0', $query->whereSql());
        $this->assertSame('title DESC', $query->orderBySql());
    }

    public function test_where_with_unknown_operator_should_throw(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Event::query()->where('capacity', '; DROP TABLE events; --', 1);
    }

    public function test_where_with_unknown_column_should_throw(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Event::query()->where('1 = 1 OR title', 'x');
    }

    public function test_where_with_non_scalar_value_should_throw(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Event::query()->where('kind', ['lecture', 'workshop']);
    }

    public function test_order_by_with_unknown_column_should_throw(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Event::query()->orderBy('starts_on; DROP TABLE events');
    }

    public function test_order_by_with_unknown_direction_should_throw(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Event::query()->orderBy('starts_on', 'ASC, (SELECT 1)');
    }
}
