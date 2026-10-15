<?php

namespace Tests\Unit\Lib;

use App\Models\User;
use InvalidArgumentException;
use Tests\TestCase;

class PaginatorTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Ordem de criação (id) diferente da ordem alfabética; as duas "Ana" empatam no nome.
        $users = [['Zé', 'ze'], ['Ana', 'ana'], ['Bruno', 'bruno'], ['Ana', 'ana2']];
        foreach ($users as [$name, $login]) {
            (new User([
                'name' => $name,
                'email' => "{$login}@example.com",
                'password' => '123456',
                'password_confirmation' => '123456'
            ]))->save();
        }
    }

    /**
     * @return array<string>
     */
    private function emails(int $page, int $per_page, ?string $order_by = null): array
    {
        $paginator = User::paginate(page: $page, per_page: $per_page, order_by: $order_by);

        return array_map(fn (User $user) => $user->email, $paginator->registers());
    }

    public function test_paginate_without_order_by_should_keep_the_insertion_order(): void
    {
        $this->assertEquals(
            ['ze@example.com', 'ana@example.com', 'bruno@example.com', 'ana2@example.com'],
            $this->emails(page: 1, per_page: 10)
        );
    }

    public function test_paginate_with_order_by_name_should_sort_alphabetically_and_untie_by_id(): void
    {
        $this->assertEquals(
            ['ana@example.com', 'ana2@example.com', 'bruno@example.com', 'ze@example.com'],
            $this->emails(page: 1, per_page: 10, order_by: 'name')
        );
    }

    public function test_paginate_with_order_by_name_should_sort_before_splitting_pages(): void
    {
        $this->assertEquals(['ana@example.com', 'ana2@example.com'], $this->emails(page: 1, per_page: 2, order_by: 'name'));
        $this->assertEquals(['bruno@example.com', 'ze@example.com'], $this->emails(page: 2, per_page: 2, order_by: 'name'));
    }

    public function test_paginate_with_unknown_order_by_column_should_throw_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        User::paginate(order_by: 'name; DROP TABLE users');
    }
}
