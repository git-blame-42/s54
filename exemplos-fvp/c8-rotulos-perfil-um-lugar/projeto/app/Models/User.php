<?php

namespace App\Models;

use Lib\Validations;
use Core\Database\ActiveRecord\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $encrypted_password
 * @property string $role
 * @property string|null $last_login_at
 */
class User extends Model
{
    protected static string $table = 'users';
    protected static array $columns = ['name', 'email', 'encrypted_password', 'role', 'last_login_at'];

    public const ROLE_ADMIN = 'admin';
    public const ROLE_PARTICIPANT = 'participant';

    /**
     * Perfis aceitos pelo ENUM da coluna `role` e o rótulo exibido de cada um.
     * Única fonte dos rótulos: o controller entrega à view, a view só percorre.
     *
     * @var array<string, string> valor do ENUM => rótulo exibido
     */
    public const ROLE_LABELS = [
        self::ROLE_ADMIN => 'Administrador',
        self::ROLE_PARTICIPANT => 'Participante',
    ];

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

    /**
     * Rótulo do perfil para a interface. Valor fora do mapa volta cru: a página
     * continua de pé e o valor desconhecido fica visível.
     */
    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }

    /**
     * Perfis que um visitante pode escolher no cadastro (B7): todos menos admin.
     *
     * @return array<string, string>
     */
    public static function signupRoleOptions(): array
    {
        $options = self::ROLE_LABELS;
        unset($options[self::ROLE_ADMIN]);

        return $options;
    }

    /**
     * Troca o perfil do usuário. Recusa valores fora do ENUM da coluna `role`
     * (no banco eles virariam PDOException).
     */
    public function changeRole(string $role): bool
    {
        if (!array_key_exists($role, self::ROLE_LABELS)) {
            return false;
        }

        return $this->update(['role' => $role]);
    }

    public function registerLogin(): bool
    {
        return $this->update(['last_login_at' => date('Y-m-d H:i:s')]);
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
