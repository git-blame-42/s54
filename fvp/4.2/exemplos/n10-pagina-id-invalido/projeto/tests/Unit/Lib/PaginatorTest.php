<?php

namespace Tests\Unit\Lib;

use App\Models\User;
use Tests\TestCase;

class PaginatorTest extends TestCase
{
    private function createUsers(int $total): void
    {
        for ($i = 1; $i <= $total; $i++) {
            $user = new User([
                'name' => "Usuário {$i}",
                'email' => "usuario{$i}@example.com",
                'password' => '123456',
                'password_confirmation' => '123456',
            ]);
            $user->save();
        }
    }

    public function test_is_out_of_range_should_be_false_for_existing_pages(): void
    {
        $this->createUsers(7);

        $this->assertFalse(User::paginate(1, 5)->isOutOfRange());
        $this->assertFalse(User::paginate(2, 5)->isOutOfRange());
    }

    public function test_is_out_of_range_should_be_true_after_the_last_page(): void
    {
        $this->createUsers(7);

        $paginator = User::paginate(3, 5);

        $this->assertTrue($paginator->isOutOfRange());
        $this->assertEquals(2, $paginator->totalOfPages());
        $this->assertSame([], $paginator->registers());
    }

    public function test_is_out_of_range_should_be_true_below_page_one(): void
    {
        $this->createUsers(2);

        $this->assertTrue(User::paginate(0, 5)->isOutOfRange());
        $this->assertTrue(User::paginate(-1, 5)->isOutOfRange());
    }

    public function test_page_one_of_an_empty_table_should_not_be_out_of_range(): void
    {
        $paginator = User::paginate(1, 5);

        $this->assertEquals(0, $paginator->totalOfPages());
        $this->assertFalse($paginator->isOutOfRange());
        $this->assertTrue(User::paginate(2, 5)->isOutOfRange());
    }
}
