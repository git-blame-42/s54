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
 * @property string|null $login_code_digest
 * @property string|null $login_code_expires_at
 * @property int|string|null $login_code_attempts
 */
class User extends Model
{
    /**
     * Login por código (FVP B11): validade do código em segundos e número máximo de
     * tentativas erradas antes de o código morrer. Constantes no model, não no
     * controller: são regra de domínio, e os testes unitários as usam.
     */
    public const LOGIN_CODE_TTL = 300;
    public const LOGIN_CODE_MAX_ATTEMPTS = 3;

    protected static string $table = 'users';
    protected static array $columns = [
        'name',
        'email',
        'encrypted_password',
        'role',
        'last_login_at',
        'login_code_digest',
        'login_code_expires_at',
        'login_code_attempts',
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

        // save() envia todas as colunas de $columns; NULL em login_code_attempts
        // (NOT NULL) faria o INSERT falhar. Mesma lição do padrão de role, acima.
        if ($this->login_code_attempts === null) {
            $this->login_code_attempts = 0;
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

    /* ------------------- Login por código (FVP B11) ------------------- */

    /**
     * Sorteia um código de 4 dígitos (1000 a 9999), grava só o hash e a validade,
     * zera as tentativas e devolve o código em texto puro: é o único momento em que
     * ele existe fora do navegador (o controller o mostra na tela; em produção iria
     * por e-mail). Pedir um código novo invalida o anterior (hash sobrescrito).
     *
     * Só para Usuário já salvo: update() precisa do id. random_int é o gerador
     * criptográfico; é a única linha sobre "geração", o resto deste bloco é validação.
     * Próximo passo: hasPendingLoginCode() e authenticateWithLoginCode(), abaixo.
     */
    public function generateLoginCode(): string
    {
        $code = (string) random_int(1000, 9999);

        $this->update([
            'login_code_digest' => password_hash($code, PASSWORD_DEFAULT),
            'login_code_expires_at' => date('Y-m-d H:i:s', time() + self::LOGIN_CODE_TTL),
            'login_code_attempts' => 0,
        ]);

        return $code;
    }

    /**
     * Há código gravado, dentro da validade e com tentativas sobrando? Três das
     * verificações do login por código num só lugar; o controller também usa para
     * decidir entre "tente de novo" e "peça outro código". Validade comparada em PHP
     * (mesmo relógio que a gerou), nunca com NOW() do MySQL.
     */
    public function hasPendingLoginCode(): bool
    {
        return $this->login_code_digest !== null
            && strtotime((string) $this->login_code_expires_at) > time()
            && (int) $this->login_code_attempts < self::LOGIN_CODE_MAX_ATTEMPTS;
    }

    /**
     * A validação do login por código. Devolve true uma única vez por código.
     *
     * 1. existe código pendente, 2. dentro da validade, 3. tentativas < máximo
     *    (hasPendingLoginCode); se falhar, limpa o que sobrou e recusa;
     * 4. forma: exatamente 4 dígitos (rejeita '12345', ' 1234', 'abcd' sem rodar bcrypt);
     * 5. hash confere (password_verify: tempo constante; nunca ==, que converte '1e3');
     * 6. erro conta tentativa no banco (sobrevive à troca de navegador); na última, limpa;
     * 7. sucesso limpa o código antes de devolver: uso único.
     * A oitava verificação, "este navegador pediu este código", fica em Auth
     * (lib/Authentication/Auth.php), que é quem cuida da sessão.
     * Próximo passo: tests/Unit/Models/Users/UserTest.php.
     */
    public function authenticateWithLoginCode(string $code): bool
    {
        if (!$this->hasPendingLoginCode()) {
            $this->clearLoginCode();
            return false;
        }

        $wellFormed = preg_match('/^\d{4}$/', $code) === 1;
        if (!$wellFormed || !password_verify($code, (string) $this->login_code_digest)) {
            $attempts = (int) $this->login_code_attempts + 1;
            if ($attempts >= self::LOGIN_CODE_MAX_ATTEMPTS) {
                $this->clearLoginCode();
            } else {
                $this->update(['login_code_attempts' => $attempts]);
            }
            return false;
        }

        $this->clearLoginCode();
        return true;
    }

    /** Apaga o código pendente: "não há pedido" é digest NULL, sem coluna extra. */
    private function clearLoginCode(): void
    {
        $this->update([
            'login_code_digest' => null,
            'login_code_expires_at' => null,
            'login_code_attempts' => 0,
        ]);
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
