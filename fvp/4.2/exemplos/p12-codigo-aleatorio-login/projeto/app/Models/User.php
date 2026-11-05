<?php

namespace App\Models;

use Lib\Validations;
use Core\Database\ActiveRecord\HasMany;
use Core\Database\ActiveRecord\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $encrypted_password
 * @property string $role
 * @property string|null $last_login_at
 * @property int|string|null $login_code código de acesso pendente (P12)
 * @property string|null $login_code_expires_at
 */
class User extends Model
{
    protected static string $table = 'users';
    protected static array $columns = [
        'name',
        'email',
        'encrypted_password',
        'role',
        'last_login_at',
        'login_code',
        'login_code_expires_at',
    ];

    /** Validade do código de acesso, em segundos. */
    public const LOGIN_CODE_TTL = 300;

    protected ?string $password = null;
    protected ?string $password_confirmation = null;

    /**
     * @param array<string, mixed> $params
     */
    public function __construct($params = [])
    {
        parent::__construct($params);

        if ($this->role === null) {
            $this->role = 'participant';
        }
    }

    public function validates(): void
    {
        Validations::notEmpty('name', $this);
        Validations::notEmpty('email', $this);

        Validations::uniqueness('email', $this);

        if ($this->newRecord()) {
            Validations::passwordConfirmation($this);
        }
    }

    /**
     * Eventos criados por este usuário (Administrador).
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'user_id');
    }

    public function authenticate(string $password): bool
    {
        if ($this->encrypted_password == null) {
            return false;
        }

        return password_verify($password, $this->encrypted_password);
    }

    public static function findByEmail(string $email): User | null
    {
        return User::findBy(['email' => $email]);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function registerLogin(): bool
    {
        return $this->update(['last_login_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Gera e grava o código de acesso (1000 a 9999) pedido depois da senha. Substitui o anterior.
     */
    public function generateLoginCode(): int
    {
        $code = random_int(1000, 9999);
        $this->update([
            'login_code' => $code,
            'login_code_expires_at' => date('Y-m-d H:i:s', time() + self::LOGIN_CODE_TTL),
        ]);

        return $code;
    }

    /**
     * Confere o código digitado. Certo e dentro da validade: apaga o código (uso único) e retorna true.
     */
    public function verifyLoginCode(string $code): bool
    {
        if ($this->login_code === null || $this->login_code_expires_at === null) {
            return false;
        }

        $expired = strtotime($this->login_code_expires_at) < time();

        if ($expired || !ctype_digit($code) || (int) $code !== (int) $this->login_code) {
            return false;
        }

        return $this->update(['login_code' => null, 'login_code_expires_at' => null]);
    }

    public function __set(string $property, mixed $value): void
    {
        parent::__set($property, $value);

        if (
            $property === 'password' &&
            $this->newRecord() &&
            $value !== null && $value !== ''
        ) {
            $this->encrypted_password = password_hash($value, PASSWORD_DEFAULT);
        }
    }
}
