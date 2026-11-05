<?php

namespace Tests\Unit\Models\Users;

use App\Models\User;
use Tests\TestCase;

class UserSearchTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->createUser('Maria Souza', 'maria@example.com');
        $this->createUser('Ana Lima', 'contato@empresa.com');
    }

    public function test_search_should_match_only_by_name(): void
    {
        $users = User::search('Souza');

        $this->assertCount(1, $users);
        $this->assertEquals('Maria Souza', $users[0]->name);
    }

    public function test_search_should_match_only_by_email(): void
    {
        $users = User::search('empresa');

        $this->assertCount(1, $users);
        $this->assertEquals('contato@empresa.com', $users[0]->email);
    }

    public function test_search_should_return_empty_array_when_nothing_matches(): void
    {
        $this->assertSame([], User::search('inexistente'));
    }

    public function test_search_should_return_results_ordered_by_name(): void
    {
        // "a" casa com os dois; Ana vem antes de Maria, embora tenha sido criada depois.
        $names = array_map(fn ($user) => $user->name, User::search('a'));

        $this->assertEquals(['Ana Lima', 'Maria Souza'], $names);
    }

    private function createUser(string $name, string $email): void
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $user->save();
    }
}
