<?php

namespace App\Models;

use Lib\Validations;
use Core\Database\ActiveRecord\Model;
use Core\Database\Database;
use PDO;

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

    /**
     * Busca usuários cujo nome ou e-mail contém o termo, ordenados por nome.
     *
     * O termo vai sempre por bind; nunca é interpolado no SQL.
     *
     * @return array<User>
     */
    public static function search(string $term): array
    {
        $columns = implode(', ', static::$columns);
        $table = static::$table;

        $sql = <<<SQL
            SELECT id, {$columns} FROM {$table}
            WHERE name LIKE :term OR email LIKE :term
            ORDER BY name;
        SQL;

        $stmt = Database::getDatabaseConn()->prepare($sql);
        $stmt->bindValue(':term', "%{$term}%");
        $stmt->execute();

        return array_map(
            fn ($row) => new static($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
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
