<?php

namespace App\Models;

use Lib\Validations;
use Core\Database\ActiveRecord\Model;
use Core\Database\Database;
use DateTimeImmutable;

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
            $this->role = 'user';
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

    public function registerLogin(): bool
    {
        return $this->update(['last_login_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Data de cadastro, lida direto do banco. `created_at` fica fora de `$columns`
     * de propósito: lá dentro, save() mandaria NULL para uma coluna NOT NULL.
     */
    public function createdAt(string $format = 'Y-m-d H:i:s'): ?string
    {
        if ($this->newRecord()) {
            return null;
        }

        $table = static::$table;
        $stmt = Database::getDatabaseConn()->prepare("SELECT created_at FROM {$table} WHERE id = :id");
        $stmt->bindValue(':id', $this->id);
        $stmt->execute();

        $createdAt = $stmt->fetchColumn();
        if ($createdAt === false || $createdAt === null) {
            return null;
        }

        return (new DateTimeImmutable((string) $createdAt))->format($format);
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
