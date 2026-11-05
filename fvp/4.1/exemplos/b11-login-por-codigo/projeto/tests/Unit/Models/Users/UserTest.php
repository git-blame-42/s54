<?php

namespace Tests\Unit\Models\Users;

use App\Models\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    private User $user;
    private User $user2;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = new User([
            'name' => 'User 1',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $this->user->save();

        $this->user2 = new User([
            'name' => 'User 2',
            'email' => 'fulano1@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $this->user2->save();
    }

    public function test_should_create_new_user(): void
    {
        $this->assertCount(2, User::all());
    }

    public function test_all_should_return_all_users(): void
    {
        $this->user2->save();

        $users[] = $this->user->id;
        $users[] = $this->user2->id;

        $all = array_map(fn($user) => $user->id, User::all());

        $this->assertCount(2, $all);
        $this->assertEquals($users, $all);
    }

    public function test_destroy_should_remove_the_user(): void
    {
        $this->user->destroy();
        $this->assertCount(1, User::all());
    }

    public function test_set_id(): void
    {
        $this->user->id = 10;
        $this->assertEquals(10, $this->user->id);
    }

    public function test_set_name(): void
    {
        $this->user->name = 'User name';
        $this->assertEquals('User name', $this->user->name);
    }

    public function test_set_email(): void
    {
        $this->user->email = 'outro@example.com';
        $this->assertEquals('outro@example.com', $this->user->email);
    }

    public function test_errors_should_return_errors(): void
    {
        $user = new User();

        $this->assertFalse($user->isValid());
        $this->assertFalse($user->save());
        $this->assertTrue($user->hasErrors());

        $this->assertEquals('não pode ser vazio!', $user->errors('name'));
        $this->assertEquals('não pode ser vazio!', $user->errors('email'));
    }

    public function test_errors_should_return_password_confirmation_error(): void
    {
        $user = new User([
            'name' => 'User 3',
            'email' => 'fulano3@example.com',
            'password' => '123456',
            'password_confirmation' => '1234567'
        ]);

        $this->assertFalse($user->isValid());
        $this->assertFalse($user->save());

        $this->assertEquals('as senhas devem ser idênticas!', $user->errors('password'));
    }

    public function test_find_by_id_should_return_the_user(): void
    {
        $this->assertEquals($this->user->id, User::findById($this->user->id)->id);
    }

    public function test_find_by_id_should_return_null(): void
    {
        $this->assertNull(User::findById(3));
    }

    public function test_find_by_email_should_return_the_user(): void
    {
        $this->assertEquals($this->user->id, User::findByEmail($this->user->email)->id);
    }

    public function test_find_by_email_should_return_null(): void
    {
        $this->assertNull(User::findByEmail('not.exits@example.com'));
    }

    public function test_authenticate_should_return_the_true(): void
    {
        $this->assertTrue($this->user->authenticate('123456'));
        $this->assertFalse($this->user->authenticate('wrong'));
    }

    public function test_authenticate_should_return_false(): void
    {
        $this->assertFalse($this->user->authenticate(''));
    }

    public function test_is_admin_should_return_false_by_default(): void
    {
        $this->assertEquals('participant', User::findById($this->user->id)->role);
        $this->assertFalse($this->user->isAdmin());
    }

    public function test_is_admin_should_return_true_for_admin_role(): void
    {
        $this->user->role = 'admin';
        $this->user->save();

        $this->assertTrue(User::findById($this->user->id)->isAdmin());
    }

    public function test_last_login_at_should_be_null_by_default(): void
    {
        $this->assertNull(User::findById($this->user->id)->last_login_at);
    }

    public function test_register_login_should_set_last_login_at(): void
    {
        $this->user->registerLogin();

        $this->assertNotNull(User::findById($this->user->id)->last_login_at);
    }

    public function test_update_should_not_change_the_password(): void
    {
        $this->user->password = '654321';
        $this->user->save();

        $this->assertTrue($this->user->authenticate('123456'));
        $this->assertFalse($this->user->authenticate('654321'));
    }

    /* ------------------- Login por código (FVP B11) ------------------- */
    // Cada teste cobre um dos pontos de validação do model. $this->user é o usuário
    // salvo no setUp. Próximo passo: lib/Authentication/Auth.php (pedido na sessão).

    public function test_generate_login_code_should_return_four_digits_and_store_only_a_digest(): void
    {
        $code = $this->user->generateLoginCode();
        $stored = User::findById($this->user->id);

        $this->assertMatchesRegularExpression('/^\d{4}$/', $code);
        $this->assertGreaterThanOrEqual(1000, (int) $code);
        $this->assertLessThanOrEqual(9999, (int) $code);
        // Nunca em texto puro no banco: só o hash, que confere com o código.
        $this->assertNotEquals($code, $stored->login_code_digest);
        $this->assertTrue(password_verify($code, (string) $stored->login_code_digest));
        $this->assertGreaterThan(time(), strtotime((string) $stored->login_code_expires_at));
        $this->assertEquals(0, $stored->login_code_attempts);
        $this->assertTrue($stored->hasPendingLoginCode());
    }

    public function test_authenticate_with_login_code_should_accept_the_code_only_once(): void
    {
        $code = $this->user->generateLoginCode();

        $this->assertTrue($this->user->authenticateWithLoginCode($code));
        $this->assertNull(User::findById($this->user->id)->login_code_digest);
        $this->assertFalse($this->user->authenticateWithLoginCode($code));
    }

    public function test_authenticate_with_login_code_should_reject_a_wrong_code_and_count_the_attempt(): void
    {
        $code = $this->user->generateLoginCode();

        $this->assertFalse($this->user->authenticateWithLoginCode($this->wrongCode($code)));
        $this->assertEquals(1, User::findById($this->user->id)->login_code_attempts);
        // O código certo ainda vale enquanto sobram tentativas.
        $this->assertTrue($this->user->authenticateWithLoginCode($code));
    }

    public function test_authenticate_with_login_code_should_lock_after_three_wrong_attempts(): void
    {
        $code = $this->user->generateLoginCode();

        for ($i = 0; $i < User::LOGIN_CODE_MAX_ATTEMPTS; $i++) {
            $this->assertFalse($this->user->authenticateWithLoginCode($this->wrongCode($code)));
        }

        $this->assertFalse($this->user->hasPendingLoginCode());
        $this->assertNull(User::findById($this->user->id)->login_code_digest);
        $this->assertFalse($this->user->authenticateWithLoginCode($code));
    }

    public function test_authenticate_with_login_code_should_reject_an_expired_code(): void
    {
        $code = $this->user->generateLoginCode();
        // update() não valida: serve para plantar a validade no passado.
        $this->user->update(['login_code_expires_at' => date('Y-m-d H:i:s', time() - 1)]);

        $this->assertFalse($this->user->authenticateWithLoginCode($code));
        $this->assertNull(User::findById($this->user->id)->login_code_digest);
    }

    public function test_authenticate_with_login_code_should_reject_malformed_input_and_no_pending_code(): void
    {
        $this->assertFalse($this->user->authenticateWithLoginCode('1234'));

        $this->user->generateLoginCode();
        foreach (['', '12', '12345', 'abcd', ' 1234'] as $bad) {
            $this->assertFalse($this->user->authenticateWithLoginCode($bad), var_export($bad, true));
        }
    }

    /** Um código certamente diferente do sorteado, ainda com 4 dígitos. */
    private function wrongCode(string $code): string
    {
        return $code === '1000' ? '1001' : '1000';
    }
}
