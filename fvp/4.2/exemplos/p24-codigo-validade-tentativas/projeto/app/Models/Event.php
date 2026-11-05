<?php

namespace App\Models;

use Core\Database\ActiveRecord\BelongsTo;
use Core\Database\ActiveRecord\Model;
use Lib\CodeCheck;
use Lib\Validations;
use PDOException;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $description
 * @property string $kind
 * @property string $modality
 * @property string|null $audience valores separados por vírgula (tipo SET do MySQL); use audienceList()
 * @property string $starts_on
 * @property string $location
 * @property int|string $capacity
 * @property int|string|null $confirmation_code gerado no construtor quando vazio; nunca vem do formulário
 * @property int|string|null $confirmation_code_issued_at instante (epoch) em que o código atual foi gerado
 * @property int|string|null $confirmation_code_attempts tentativas erradas com o código atual
 */
class Event extends Model
{
    /** Valores do ENUM `kind`, com o rótulo exibido nos formulários. */
    public const KINDS = [
        'lecture' => 'Palestra',
        'workshop' => 'Oficina',
        'short_course' => 'Minicurso',
        'academic_week' => 'Semana acadêmica',
    ];

    /** Valores do ENUM `modality`, com o rótulo exibido nos formulários. */
    public const MODALITIES = [
        'in_person' => 'Presencial',
        'online' => 'Online',
        'hybrid' => 'Híbrido',
    ];

    /** Valores do SET `audience`, com o rótulo exibido nos formulários. */
    public const AUDIENCES = [
        'students' => 'Estudantes',
        'professors' => 'Professores',
        'staff' => 'Servidores',
        'community' => 'Comunidade externa',
    ];

    /** Faixa do código de confirmação (4 dígitos). */
    public const CODE_MIN = 1000;
    public const CODE_MAX = 9999;

    /** O código vale 5 minutos e aceita 3 tentativas erradas. */
    public const CODE_TTL_SECONDS = 300;
    public const CODE_MAX_ATTEMPTS = 3;

    protected static string $table = 'events';
    protected static array $columns = [
        'user_id',
        'title',
        'description',
        'kind',
        'modality',
        'audience',
        'starts_on',
        'location',
        'capacity',
        'confirmation_code',
        'confirmation_code_issued_at',
        'confirmation_code_attempts',
    ];

    /**
     * Sorteia o código de confirmação só se ele estiver vazio. O construtor também roda a cada leitura do
     * banco (findById, where, Paginator); com o "só se vazio", o código lido nunca é trocado.
     *
     * @param array<string, mixed> $params
     */
    public function __construct($params = [])
    {
        parent::__construct($params);

        $code = $this->confirmation_code;
        if ($code === null || $code === '') {
            $this->confirmation_code = random_int(self::CODE_MIN, self::CODE_MAX);
        }

        // save() grava todas as colunas e ignora DEFAULT: o instante e as tentativas precisam de valor.
        if ($this->confirmation_code_issued_at === null || $this->confirmation_code_issued_at === '') {
            $this->confirmation_code_issued_at = time();
        }
        if ($this->confirmation_code_attempts === null || $this->confirmation_code_attempts === '') {
            $this->confirmation_code_attempts = 0;
        }
    }

    /**
     * Confere o código digitado sem conversão: só string de dígitos igual ao código ('04321', ' 4321',
     * null e array não valem). hash_equals compara strings, sem as conversões do ==.
     */
    public function confirmsCode(mixed $input): bool
    {
        if (!is_string($input) || !ctype_digit($input)) {
            return false;
        }

        return hash_equals((string) $this->confirmation_code, $input);
    }

    /**
     * Gera outro código, com validade a partir de $now e tentativas zeradas. Em registro salvo, grava na hora.
     *
     * $now é o relógio (epoch): os testes passam um instante fixo; em produção fica null e vale time().
     */
    public function issueConfirmationCode(?int $now = null): int
    {
        $code = random_int(self::CODE_MIN, self::CODE_MAX);
        $data = [
            'confirmation_code' => $code,
            'confirmation_code_issued_at' => $now ?? time(),
            'confirmation_code_attempts' => 0,
        ];

        if ($this->newRecord()) {
            foreach ($data as $column => $value) {
                $this->$column = $value;
            }
        } else {
            // update() devolve false se nada mudou; aqui o retorno não indica erro.
            $this->update($data);
        }

        return $code;
    }

    /**
     * Código que ainda vale. Se o atual expirou ou esgotou as tentativas, gera outro; senão devolve o mesmo
     * sem zerar as tentativas (recarregar a página não dá tentativas novas).
     */
    public function currentConfirmationCode(?int $now = null): int
    {
        if ($this->confirmationCodeState($now ?? time()) === null) {
            return $this->codeAttribute('confirmation_code');
        }

        return $this->issueConfirmationCode($now);
    }

    /**
     * Confere o código digitado. Ok invalida o código (uso único); errado conta uma tentativa (inclusive
     * vazio, null ou array) e a terceira já devolve Exhausted. Código esgotado ou expirado nem é comparado.
     */
    public function checkConfirmationCode(mixed $given, ?int $now = null): CodeCheck
    {
        $state = $this->confirmationCodeState($now ?? time());
        if ($state !== null) {
            return $state;
        }

        if ($this->confirmsCode($given)) {
            $this->update(['confirmation_code_issued_at' => 0]);
            return CodeCheck::Ok;
        }

        $attempts = $this->codeAttribute('confirmation_code_attempts') + 1;
        $this->update(['confirmation_code_attempts' => $attempts]);

        return $attempts >= self::CODE_MAX_ATTEMPTS ? CodeCheck::Exhausted : CodeCheck::Wrong;
    }

    /**
     * null se o código ainda vale; senão o motivo. Esgotado vem antes de expirado.
     */
    private function confirmationCodeState(int $now): ?CodeCheck
    {
        if ($this->codeAttribute('confirmation_code_attempts') >= self::CODE_MAX_ATTEMPTS) {
            return CodeCheck::Exhausted;
        }

        if ($now - $this->codeAttribute('confirmation_code_issued_at') > self::CODE_TTL_SECONDS) {
            return CodeCheck::Expired;
        }

        return null;
    }

    /**
     * Valor inteiro de uma coluna do código (o banco devolve int; atribuído à mão pode vir string).
     */
    private function codeAttribute(string $column): int
    {
        $value = filter_var($this->$column, FILTER_VALIDATE_INT);

        return $value === false ? 0 : $value;
    }

    public function validates(): void
    {
        Validations::notEmpty('title', $this);
        Validations::notEmpty('description', $this);
        Validations::notEmpty('location', $this);

        Validations::notEmpty('kind', $this) &&
            Validations::inclusion('kind', $this, array_keys(self::KINDS));

        Validations::notEmpty('modality', $this) &&
            Validations::inclusion('modality', $this, array_keys(self::MODALITIES));

        Validations::notEmpty('audience', $this) &&
            Validations::inclusionOfAll('audience', $this, array_keys(self::AUDIENCES));

        Validations::notEmpty('starts_on', $this) &&
            Validations::validDate('starts_on', $this) &&
            $this->validatesStartsOnNotPast();

        if (Validations::notEmpty('capacity', $this) && Validations::integerMin('capacity', $this, 1)) {
            $this->validatesCapacityAgainstEnrollments();
        }
    }

    /**
     * A data não pode estar no passado ao criar. Na edição, só é cobrada quando `starts_on` muda:
     * assim um Evento que já aconteceu ainda pode ter a descrição, o local etc. corrigidos.
     */
    private function validatesStartsOnNotPast(): void
    {
        if (!$this->newRecord()) {
            $original = self::findById($this->id);

            if ($original !== null && $original->starts_on === $this->starts_on) {
                return;
            }
        }

        Validations::notPastDate('starts_on', $this);
    }

    /**
     * P02: na edição, a capacidade não pode ficar abaixo do total de Inscrições.
     */
    private function validatesCapacityAgainstEnrollments(): void
    {
        if ($this->newRecord()) {
            return;
        }

        $enrolled = $this->enrollmentsCount();

        if ((int) $this->capacity < $enrolled) {
            $this->addError('capacity', "não pode ser menor que o total de inscrições ({$enrolled})!");
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Total de Inscrições (linhas em event_user) deste Evento.
     */
    public function enrollmentsCount(): int
    {
        if ($this->newRecord()) {
            return 0;
        }

        return count(Enrollment::where(['event_id' => $this->id]));
    }

    /**
     * Vagas restantes (capacity - Inscrições), nunca negativo. Calculado, não armazenado.
     */
    public function availableSpots(): int
    {
        return max(0, (int) $this->capacity - $this->enrollmentsCount());
    }

    public function hasEnrollments(): bool
    {
        return $this->enrollmentsCount() > 0;
    }

    /**
     * Remove o Evento sem lançar exceção (P03): se houver Inscrições, não remove,
     * retorna false e registra o motivo em errors('base').
     */
    public function safeDestroy(): bool
    {
        $this->errors = [];

        if ($this->newRecord()) {
            $this->addError('base', 'o evento não existe e não pode ser removido!');
            return false;
        }

        $enrolled = $this->enrollmentsCount();
        if ($enrolled > 0) {
            $this->addError('base', "não é possível remover um evento com inscrições ({$enrolled})!");
            return false;
        }

        try {
            return $this->destroy();
        } catch (PDOException $e) {
            // FK RESTRICT: alguém se inscreveu entre a contagem e o DELETE.
            $this->addError('base', 'não foi possível remover o evento, pois ele possui inscrições!');
            return false;
        }
    }

    /**
     * Público-alvo como array (o banco guarda "students,professors").
     *
     * @return array<int, string>
     */
    public function audienceList(): array
    {
        $audience = $this->audience;

        if ($audience === null || $audience === '') {
            return [];
        }

        return explode(',', $audience);
    }

    /**
     * Define o público-alvo a partir de um array (ex.: checkboxes `audience[]`).
     * Remove duplicados e valores vazios; valores fora de AUDIENCES são mantidos
     * para que a validação os rejeite.
     *
     * @param array<int, mixed> $values
     */
    public function setAudienceList(array $values): void
    {
        $values = array_values(array_unique(array_filter(
            array_map('strval', $values),
            fn(string $value) => $value !== ''
        )));

        $this->audience = implode(',', $values);
    }

    public function hasAudience(string $value): bool
    {
        return in_array($value, $this->audienceList(), true);
    }
}
